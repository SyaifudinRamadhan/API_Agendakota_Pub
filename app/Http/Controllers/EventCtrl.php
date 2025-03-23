<?php

namespace App\Http\Controllers;

use App\Models\AvailableDayTicketSell;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Breakdown;
use App\Models\EventThumbnail;
use App\Models\LimitReschedule;
use App\Models\Organization;
use App\Models\Purchase;
use App\Models\Ticket;
use App\Models\Voucher;
use DateInterval;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class EventCtrl extends Controller
{
    /* ===================== NOTE (IMPORTANT !!!) ===================
      -> Category event is divided by 2 group 
      -> First, group of event by end time or expire time 
      -> Second, group of event daily. This group containing three event categories :
        1. Attraction
        2. Daily Activities 
        3. Tour Travel (recurring)
      -> The first group, is the left of the existing list of categories 
    =================================================================*/
    private function validateThumbnails(Request $req, int $limitSize)
    {
        if(!$req->hasFile('thumbnails')){
            return null;
        }
        $files = $req->file('thumbnails');
        $valid = true;
        if(is_array($files)){
            for ($i=0; $i < count($files); $i++) { 
                if($files[$i]->filesize > $limitSize || ($files[$i]->filetype != 'image/png' && $files[$i]->filetype != 'image/PNG' && $files[$i]->filetype != 'image/jpeg' && $files[$i]->filetype != 'image/JPEG')){
                    $valid = false;
                    $i = count($files);
                }
            }
        }else{
            if($files->filesize > $limitSize || ($files->filetype != 'image/png' && $files->filetype != 'image/PNG' && $files->filetype != 'image/jpeg' && $files->filetype != 'image/JPEG')){
                $valid = false;
            }else{
                $files = [$files];
            }
        }
        return ["isValid" => $valid, "data" => $files];
    }

    private function createThumbnails(Request $req, String $eventId, Array $files)
    {
        $insert = [];
        for ($i=0; $i < count($files); $i++) { 
            $fileName = BasicFunctional::randomStr(5).'_'.time().'.'.$files[$i]->getCLientOriginalExtension();
            $files[$i]->storeAs('public/event_banners', $fileName);
            $fileName = "/storage/event_banners/".$fileName;
            array_push($insert, [
                'event_id' => $eventId,
                'image' => $fileName,
                'priority' => $i
            ]);
        }
        EventThumbnail::create($insert);   
    }

    private function updateThumbnails(Request $req, Array $lastOrder, Array $newImages, Array $newImgOrder)
    {
        /*
            ---------------------- Variable structure ------------------------
            -> lasOrder => [
                ['image name','oder number'], ['image name 2', 'order number 2'], ...
            ]
            -> newImages => Array files php ([file1, file2, ...])
            -> newImgOrder => [new image/file 1 order number, new image/file 2 order number, ...]
            -------------------------------------------------------------------
        */
        $data = [];
        // prepare new images
        for ($i=0; $i < count($newImages); $i++) { 
            $fileName = BasicFunctional::randomStr(5).'_'.time().'.'.$newImages[$i]->getClientOriginalExtension();
            $newImages[$i]->storeAs('publuc/event_banners', $fileName);
            $fileName = "/storage/event_banners/".$fileName;
            // update image on lastOrder with new file image
            for ($j=0; $j < count($lastOrder); $j++) { 
                if($lastOrder[$j][1] == $newImgOrder[$i]){
                    $lastOrder[$j][0] = $fileName;
                    if($i+1 < count($newImages)){
                        $j = count($lastOrder);
                    }
                }
                if($i+1 === count($newImages)){
                    array_push($data, [
                        'event_id' => $req->event_id,
                        'image' => $fileName,
                        'priority' => $lastOrder[$i][1]
                    ]);
                }
            }
        }
        EventThumbnail::where('event_id', $req->event->id)->delete();
        EventThumbnail::create($data);
    }
    
    public function create(Request $req, $orgId)
    {
        // $pkg = PkgPricing::where('id', $req->pkg_id)->where('deleted',  0)->first();
        // if(!$pkg){
        //     return response()->json(["error" => "You have selected package is not found"], 404);
        // }
        $rule = [
            'name' => 'required|string',
            'category' => 'required|string',
            'topics' => 'required|string',
            'logo' => 'required|image|max:2048',
            'desc' => 'required|string',
            'snk' => 'required|string',
            'exe_type' => 'required|string', // handled
            'location' => 'required|string',
            'province' => 'required|string',
            'city' => 'required|string',
            'instagram' => 'required|string',
            'twitter' => 'required|string',
            'website' => 'required|string',
            'twn_url' => 'required|string',
            'seat_map' => 'image|max:2048',
            'available_days' => 'array',
            'daily_limit_times' => 'array',
            'daily_start_times' => 'array',
            'custom_fields' => 'array',
            'visibility' => 'required|boolean',
            "allow_refund" => "boolean",
            'single_trx' => 'boolean',
            'is_publish' => 'boolean'
        ];
        // if($pkg->price != 0){
        //     $rule += [
        //         'pay_method' => 'required|string'
        //     ];
        // }
        $validator = Validator::make($req->all(), $rule);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        // Must be validated manually
        // 1. date time input 
        // 2. exe_type input (online, offline, hybrid)
        // if((intval($req->pay_method) == 14 && !$req->mobile_number) || (intval($req->pay_method) == 15 && !$req->cashtag)){
        //     return response()->json(["error" => "mobile number is required for pay method with OVO or $"."cashtag is required for pay method with JeniusPay"], 403);
        // }
        $start = null;
        $end = null;
        if ($req->exe_type !== 'online' && $req->exe_type !== 'offline' && $req->exe_type !== 'hybrid') {
            return response()->json(['error' => 'Your input of execution type is not available. Please input "online" or "hybrid" or "offline" for this field'], 400);
        }
        if (($req->category == 'Attraction' || $req->category == 'Tour Travel' || $req->category == 'Daily Activities' || $req->category == 'Tour Travel (recurring)') && $req->exe_type != 'offline') {
            return response()->json(['error' => 'Event with category ' . $req->category . ' only available for offline type'], 403);
        }
        $limitTime = [];
        $startTime = [];
        if (($req->category != 'Attraction' && $req->category != 'Daily Activities' && $req->category != 'Tour Travel (recurring)')) {
            if (!$req->start_date || !$req->end_date || !$req->start_time || !$req->end_time) {
                return response()->json(['error' => 'Date time or field start_date, start_time, end_date, and end_time is required for this event category'], 403);
            }
            try {
                $start = new DateTime($req->start_date . ' ' . $req->start_time, new DateTimeZone('Asia/Jakarta'));
                $end = new DateTime($req->end_date . ' ' . $req->end_time, new DateTimeZone('Asia/Jakarta'));
            } catch (\Throwable $th) {
                return response()->json(['error' => 'Invalid input date time for start or end date time'], 400);
            }
            if ($start < new DateTime('now', new DateTimeZone('Asia/Jakarta')) || $start >= $end) {
                return response()->json(["error" => "Start time can't les then current time or greater than end time of event"], 400);
            }
        } else {
            if (!$req->daily_limit_times || !$req->daily_start_times) {
                return response()->json(["error" => "Ticket for daily event, must be have a max limit time available per days"], 403);
            }
            if (count($req->available_days) != count($req->daily_limit_times) || count($req->available_days) != count($req->daily_start_times)) {
                return response()->json(["error" => "Count of available days is not match with daily limit time"], 403);
            }
            foreach ($req->daily_limit_times as $key => $daily_limit_time) {
                try {
                    $limitTime[$key] = new DateTime($daily_limit_time, new DateTimeZone('Asia/Jakarta'));
                    $startTime[$key] = new DateTime($req->daily_start_times[$key], new DateTimeZone('Asia/Jakarta'));
                } catch (\Throwable $th) {
                    return response()->json(["error" => "Format daily limit time is H:i (hour:minute)"], 403);
                }
                if ($limitTime[$key]->format("H:i") != $daily_limit_time || $startTime[$key]->format("H:i") != $req->daily_start_times[$key]) {
                    return response()->json(["error" => "Format daily limit time is H:i (hour:minute) format"], 403);
                }
            }
            $start = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $end = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $end->add(new DateInterval('P1Y'));
        }
        // $eventSameTime = Event::where('org_id', $orgId)->where('start_date', $start->format('Y-m-d'))->get();
        // if(count($eventSameTime) >= $pkg->event_same_time){
        //     return response()->json(["error" => "You have created ".count($eventSameTime)." events with start date in same day. This package only receive ".$pkg->event_same_time." events in same start date"], 403);
        // }
        // $originNameFile = pathinfo($req->file('logo')->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('logo')->getClientOriginalExtension();
        $req->file('logo')->storeAs('public/event_banners', $fileName);
        $fileName = "/storage/event_banners/" . $fileName;
        $fields = null;
        if ($req->custom_fields) {
            foreach ($req->custom_fields as $value) {
                if ($fields == null) $fields = $value;
                else $fields = $fields . '|' . $value;
            }
        }
        $seatMapImage = null;
        if ($req->hasFile('seat_map')) {
            // $seatMapImage = pathinfo($req->file('seat_map')->getClientOriginalName(), PATHINFO_FILENAME);
            $seatMapImage = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('seat_map')->getClientOriginalExtension();
            $req->file('seat_map')->storeAs('public/seat_maps', $seatMapImage);
            $seatMapImage = '/storage/seat_maps/' . $seatMapImage;
        }
        $event = Event::create([
            'org_id' => $orgId,
            'slug' => Str::slug($req->name),
            'name' => $req->name,
            'category' => $req->category,
            'topics' => $req->topics,
            'logo' => $fileName,
            'desc' => $req->desc,
            'snk' => $req->snk,
            'exe_type' => $req->exe_type,
            'location' => $req->location,
            'province' => $req->province,
            'city' => $req->city,
            'start_date' => $start->format("Y-m-d"),
            'end_date' => $end->format("Y-m-d"),
            'start_time' => $start->format("H:i:s"),
            'end_time' => $end->format("H:i:s"),
            'is_publish' => !$req->is_publish || $req->is_publish == false ? 1 : 2,
            'instagram' => $req->instagram,
            'twitter' => $req->twitter,
            'website' => $req->website,
            'twn_url' => $req->twn_url,
            'custom_fields' => $fields,
            'single_trx' => $req->single_trx == false ? false : true,
            'seat_map' => $seatMapImage,
            'visibility' => $req->visibility,
            'allow_refund' => $req->allow_refund ? $req->allow_refund : false,
            'deleted' => 0,
        ]);
        $breakdowns = [];
        if ($req->breakdowns && ($event->exe_type == 'online' || $event->exe_type == 'hybrid')) {
            foreach ($req->breakdowns as $key => $value) {
                $breakdowns[$key] = Breakdown::create([
                    'event_id' => $event->id,
                    'name' => $value
                ]);
            }
        }
        $availableDays = [];
        if (($req->category == 'Attraction' || $req->category == 'Daily Activities' || $req->category == 'Tour Travel (recurring)') && $req->available_days) {
            $indexDup = [];
            $index = 0;
            foreach (array_count_values($req->available_days) as $value => $numDuplicated) {
                if ($numDuplicated > 1) {
                    $indexDup[] = $index;
                }
                $index++;
            }
            foreach ($req->available_days as $key => $avd) {
                if (!array_search($key, $indexDup)) {
                    $availableDays[] = AvailableDayTicketSell::create([
                        "event_id" => $event->id,
                        "day" => $avd,
                        "start_time" => $startTime[$key]->format('H:i'),
                        "max_limit_time" => $limitTime[$key]->format('H:i')
                    ]);
                }
            }
        }
        $limitReschedule = null;
        if ($req->limit_reschedule && $req->limit_reschedule != -1) {
            $limitReschedule = LimitReschedule::create([
                'event_id' => $event->id,
                'limit_time' => $req->limit_reschedule
            ]);
        }
        // $objPkgPay = new PkgPayCtrl();
        // $paymentData = $objPkgPay->createTrx($event->id, $req->pkg_id, $req->pay_method, $req->mobile_number, $req->cashtag);
        return response()->json([
            "event" => $event,
            "breakdowns" => count($breakdowns) == 0 ? null : $breakdowns,
            "available_days" => count($availableDays) == 0 ? null : $availableDays,
            "limit_available_reschedule" => $limitReschedule
            // "payment_data" => $paymentData
        ], 201);
    }
    public function update(Request $req, $orgId)
    {
        $eventObj = Event::where('id', $req->event_id)->where('org_id', $orgId)->where('is_publish', '<', 3)->where('deleted', 0);
        if (!$eventObj->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        if ($eventObj->first()->is_publish >= 3) {
            return response()->json(["error" => "This event is not active"], 403);
        }
        // $pkg = $eventObj->first()->payment()->first()->package()->first();
        $validator = Validator::make($req->all(), [
            'name' => 'required|string',
            'category' => 'required|string',
            'topics' => 'required|string',
            'logo' => 'image|max:2048',
            'desc' => 'required|string',
            'snk' => 'required|string',
            'exe_type' => 'required|string', // handled
            'location' => 'required|string',
            'province' => 'required|string',
            'city' => 'required|string',
            'instagram' => 'required|string',
            'twitter' => 'required|string',
            'website' => 'required|string',
            'twn_url' => 'required|string',
            'seat_map' => 'image|max:2048',
            'available_days' => 'array',
            'daily_limit_times' => 'array',
            'daily_start_times' => 'array',
            'custom_fields' => 'array',
            'visibility' => 'required|boolean',
            'single_trx' => 'boolean'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->erros(), 403);
        }
        if ($req->exe_type !== 'online' && $req->exe_type !== 'offline' && $req->exe_type !== 'hybrid') {
            return response()->json(['error' => 'Your input of execution type is not available. Please input "online" or "hybrid" or "offline" for this field'], 400);
        }
        if (($req->category == 'Attraction' || $req->category == 'Tour Travel' || $req->category == 'Daily Activities' || $req->category == 'Tour Travel (recurring)') && $req->exe_type != 'offline') {
            return response()->json(["error" => 'Event with category ' . $req->category . 'only avilable for offline type'], 403);
        }
        $start = null;
        $end = null;
        $limitTime = [];
        $startTime = [];
        if ($req->category != 'Attraction' && $req->category != 'Daily Activities' && $req->category != 'Tour Travel (recurring)') {
            if (!$req->start_date || !$req->start_time || !$req->end_date || !$req->end_time) {
                return response()->json(["error" => "Date time or field start_date, start_time, end_date, and end_time is required for this event category"], 403);
            }
            try {
                $start = new DateTime($req->start_date . ' ' . $req->start_time, new DateTimeZone('Asia/Jakarta'));
                $end = new DateTime($req->end_date . ' ' . $req->end_time, new DateTimeZone('Asia/Jakarta'));
            } catch (\Throwable $th) {
                return response()->json(["error" => "Invalid format input date or time"], 400);
            }
            if ($start >= $end) {
                return response()->json(["error" => "Start time can't les then current time or greater than end time of event"], 400);
            }
        } else {
            if (!$req->daily_limit_times || !$req->daily_start_times) {
                return response()->json(["error" => "Ticket for daily event, must be have a max limit time available per days"], 403);
            }
            if (count($req->available_days) != count($req->daily_limit_times) || count($req->available_days) != count($req->daily_start_times)) {
                return response()->json(["error" => "Count of available days is not match with daily limit time"], 403);
            }
            foreach ($req->daily_limit_times as $key => $daily_limit_time) {
                try {
                    $limitTime[$key] = new DateTime($daily_limit_time, new DateTimeZone('Asia/Jakarta'));
                    $startTime[$key] = new DateTime($req->daily_start_times[$key], new DateTimeZone('Asia/Jakarta'));
                } catch (\Throwable $th) {
                    return response()->json(["error" => "Format daily limit time is H:i (hour:minute)"], 403);
                }
                // if ($limitTime[$key]->format("H:i") != $daily_limit_time || $startTime[$key]->format("H:i") != $req->daily_start_times[$key]) {
                //     return response()->json(["error" => "Format daily limit time is H:i (hour:minute)"], 403);
                // }
            }
            $start = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $end = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $end->add(new DateInterval('P1Y'));
        }
        // $eventSameTime = Event::where('org_id', $eventObj->first()->org_id)->where('start_date', $start->format('Y-m-d'))->get();
        // if(count($eventSameTime) >= $pkg->event_same_time){
        //     return response()->json(["error" => "You have created ".count($eventSameTime)." events with start date in same day. This package only receive ".$pkg->event_same_time." events in same start date"], 403);
        // }
        $nameFile = $eventObj->first()->logo;
        if ($req->hasFile('logo')) {
            // $originNameFile = pathinfo($req->file('logo')->getClientOriginalName(), PATHINFO_FILENAME);
            $nameFile = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('logo')->getClientOriginalExtension();
            $req->file('logo')->storeAs('public/event_banners', $nameFile);
            $nameFile = '/storage/event_banners/' . $nameFile;
            Storage::delete('public/event_banners/' . explode('/', $eventObj->first()->logo)[3]);
        }
        $fields = $eventObj->first()->custom_fields;
        if ($req->custom_fields) {
            $fields = null;
            foreach ($req->custom_fields as $value) {
                if ($fields == null) $fields = $value;
                else $fields = $fields . '|' . $value;
            }
        }
        $seatMapImage = $eventObj->first()->seat_map;
        if ($req->hasFile('seat_map')) {
            if ($seatMapImage != null) {
                Storage::delete('public/seat_maps/' . explode('/', $seatMapImage)[3]);
            }
            // $seatMapImage = pathinfo($req->file('seat_map')->getClientOriginalName(), PATHINFO_FILENAME);
            $seatMapImage = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('seat_map')->getClientOriginalExtension();
            $req->file('seat_map')->storeAs('public/seat_maps', $seatMapImage);
            $seatMapImage = '/storage/seat_maps/' . $seatMapImage;
        }
        $updated = $eventObj->update([
            'slug' => Str::slug($req->name),
            'name' => $req->name,
            'category' => $req->category,
            'topics' => $req->topics,
            'logo' => $nameFile,
            'desc' => $req->desc,
            'snk' => $req->snk,
            'exe_type' => $req->exe_type,
            'location' => $req->location,
            'province' => $req->province,
            'city' => $req->city,
            'start_date' => $start->format("Y-m-d"),
            'end_date' => $end->format("Y-m-d"),
            'start_time' => $start->format("H:i:s"),
            'end_time' => $end->format("H:i:s"),
            'instagram' => $req->instagram,
            'twitter' => $req->twitter,
            'website' => $req->website,
            'twn_url' => $req->twn_url,
            'custom_fields' => $fields,
            'single_trx' => $req->single_trx == false ? false : true,
            'seat_map' => $seatMapImage,
            'visibility' => $req->visibility
        ]);
        Breakdown::where('event_id', $eventObj->first()->id)->delete();
        if ($req->breakdowns && ($req->exe_type == 'online' || $req->exe_type == 'hybrid')) {
            foreach ($req->breakdowns as $key => $value) {
                Breakdown::create([
                    'event_id' => $eventObj->first()->id,
                    'name' => $value
                ]);
            }
        }
        if ($req->exe_type == 'offline' && ($req->category == 'Attraction' || $req->category == 'Daily Activities' || $req->category == 'Tour Travel (recurring)')) {
            Ticket::where('event_id', $eventObj->first()->id)->update([
                'start_date' => $start->format("Y-m-d"),
                'end_date' => $end->format("Y-m-d"),
            ]);
        }
        AvailableDayTicketSell::where('event_id', $eventObj->first()->id)->delete();
        if (($req->category == 'Attraction' || $req->category == 'Daily Activities' || $req->category == 'Tour Travel (recurring)') && $req->available_days) {
            $indexDup = [];
            $index = 0;
            foreach (array_count_values($req->available_days) as $value => $numOfDuplicated) {
                if ($numOfDuplicated > 1) {
                    $indexDup[] = $index;
                }
                $index++;
            }
            foreach ($req->available_days as $key => $avd) {
                if (!array_search($key, $indexDup)) {
                    AvailableDayTicketSell::create([
                        "event_id" => $eventObj->first()->id,
                        "day" => $avd,
                        "start_time" => $startTime[$key]->format('H:i'),
                        "max_limit_time" => $limitTime[$key]->format('H:i')
                    ]);
                }
            }
        }
        if ($req->limit_reschedule == -1) {
            LimitReschedule::where('event_id', $req->event_id)->delete();
        } else if ($req->limit_reschedule) {
            $limRsc = LimitReschedule::where('event_id', $req->event_id);
            if ($limRsc->first()) {
                $limRsc->update([
                    'limit_time' => $req->limit_reschedule
                ]);
            } else {
                LimitReschedule::create([
                    'event_id' => $req->event_id,
                    'limit_time' => $req->limit_reschedule
                ]);
            }
        }
        return response()->json(["updated" => $updated], 202);
    }

    public function updatePeripheralField(Request $req, $orgId)
    {
        $eventObj = Event::where('id', $req->event_id)->where('org_id', $orgId)->where('is_publish', '<', 3)->where('deleted', 0);
        if (!$eventObj->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        if ($eventObj->first()->is_publish >= 3) {
            return response()->json(["error" => "This event is not active"], 403);
        }
        $validator = Validator::make($req->all(), [
            // in ticket data
            "limit_pchs" => "required|numeric",
            // in event data
            "single_trx" => "required|boolean",
            "remove_seat_map" => "required|boolean",
            "seat_map" => "image|max:2048",
            "custom_fields" => "array",
            // in reschedule data
            "limit_reschedule" => "numeric",
            "allow_refund" => "required|boolean"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $fields = $eventObj->first()->custom_fields;
        if ($req->custom_fields) {
            $fields = null;
            foreach ($req->custom_fields as $value) {
                if ($fields == null) $fields = $value;
                else $fields = $fields . '|' . $value;
            }
        }
        $seatMapImage = $eventObj->first()->seat_map;
        if ($req->hasFile('seat_map') && $req->remove_seat_map == false) {
            if ($seatMapImage != null) {
                Storage::delete('public/seat_maps/' . explode('/', $seatMapImage)[3]);
            }
            // $seatMapImage = pathinfo($req->file('seat_map')->getClientOriginalName(), PATHINFO_FILENAME);
            $seatMapImage = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('seat_map')->getClientOriginalExtension();
            $req->file('seat_map')->storeAs('public/seat_maps', $seatMapImage);
            $seatMapImage = '/storage/seat_maps/' . $seatMapImage;
        } else if ($req->remove_seat_map == true && ($seatMapImage != null || $seatMapImage != '')) {
            Storage::delete('public/seat_maps/' . explode('/', $seatMapImage)[3]);
            $seatMapImage = null;
        }
        Ticket::where('event_id', $req->event_id)->where('deleted', 0)->update([
            "max_purchase" => $req->limit_pchs
        ]);
        $eventObj->update([
            'custom_fields' => $fields,
            'single_trx' => $req->single_trx,
            'seat_map' => $seatMapImage,
            'allow_refund' => $req->allow_refund
        ]);
        if ($req->limit_reschedule == -1) {
            LimitReschedule::where('event_id', $req->event_id)->delete();
        } else if ($req->limit_reschedule) {

            $limRsc = LimitReschedule::where('event_id', $req->event_id);
            if ($limRsc->first()) {
                $limRsc->update([
                    'limit_time' => $req->limit_reschedule
                ]);
            } else {
                LimitReschedule::create([
                    'event_id' => $req->event_id,
                    'limit_time' => $req->limit_reschedule
                ]);
            }
        }
        return response()->json(["updated" => "Data has updated"], 202);
    }

    private function resetDateDailyType($event)
    {
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $eventEnd = new DateTime($event->end_date . ' ' . $event->end_time, new DateTimeZone('Asia/Jakarta'));
        if (($event->category != 'Attraction' && $event->category != 'Daily Activities' && $event->category != 'Tour Travel (recurring)') || $now < $eventEnd) {
            return $event;
        }
        $end =  new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $end->add(new DateInterval('P1Y'));
        Event::where('id', $event->id)->update([
            'start_date' => $now->format("Y-m-d"),
            'end_date' => $end->format("Y-m-d"),
            'start_time' => $now->format("H:i:s"),
            'end_time' => $end->format("H:i:s"),
        ]);
        Ticket::where('event_id', $event->id)->update([
            'start_date' => $now->format("Y-m-d"),
            'end_date' => $end->format("Y-m-d"),
        ]);
        return Event::where('id', $event->id)->first();
    }

    public function coreSeatNumberQtyTicket($ticket, $date)
    {
        $allSeatNumber = null;
        $reservedSeats = null;
        if ($ticket->quantity == -1) {
            $avlDays = $ticket->event()->first()->availableDays()->where('day', $date->format('D'))->first();
            $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            if ($avlDays) {
                $maxLimit = new DateTime($avlDays->max_limit_time, new DateTimeZone('Asia/Jakarta'));
                if ($now < $date || ($now->format('Y-m-d') === $date->format('Y-m-d') && $now->format("H:i") <= $maxLimit->format("H:i"))) {
                    $purchases = DB::table('purchases')
                        ->join('daily_tickets', 'daily_tickets.purchase_id', '=', 'purchases.id')
                        ->where('purchases.ticket_id', '=', $ticket->id)
                        ->where('daily_tickets.visit_date', '=', $date->format('Y-m-d'))
                        ->get();
                    $ticket->quantity = intval($ticket->limitDaily()->first()->limit_quantity) - count($purchases);
                    if ($ticket->seat_number == 1 || $ticket->seat_number == true) {
                        $allSeatNumber = range(1, intval($ticket->limitDaily()->first()->limit_quantity));
                        $reservedSeats = DB::table('purchases')
                            ->join('reserved_seats', 'reserved_seats.pch_id', '=', 'purchases.id')
                            ->join('daily_tickets', 'daily_tickets.purchase_id', '=', 'purchases.id')
                            ->select(['reserved_seats.seat_number'])
                            ->where('purchases.ticket_id', '=', $ticket->id)
                            ->where('daily_tickets.visit_date', '=', $date->format('Y-m-d'))
                            ->get();
                    }
                } else {
                    $ticket->quantity = 0;
                    $allSeatNumber = [];
                    $reservedSeats = [];
                }
            } else {
                $ticket->quantity = 0;
                $allSeatNumber = [];
                $reservedSeats = [];
            }
        } else if ($ticket->seat_number == 1 || $ticket->seat_number == true) {
            $reservedSeats = DB::table('purchases')
                ->join('reserved_seats', 'reserved_seats.pch_id', '=', 'purchases.id')
                ->select(['reserved_seats.seat_number'])
                ->where('purchases.ticket_id', '=', $ticket->id)
                ->get();
            $allSeatNumber = range(1, intval($ticket->quantity) + count($reservedSeats));
        }
        if ($ticket->seat_number == 1 || $ticket->seat_number == true) {
            $ticket->basicSeatsQty = count($allSeatNumber);
            foreach ($reservedSeats as $rsvSeat) {
                $removedIndex = array_search((int)($rsvSeat->seat_number), $allSeatNumber);
                if (gettype($removedIndex) === "integer") {
                    array_splice($allSeatNumber,  $removedIndex, 1);
                }
            }
            $ticket->available_seat_numbers = $allSeatNumber;
        }
        return $ticket;
    }

    private function getAvailableSeatNumberQty($event, $date)
    {
        $tickets = $event->tickets()->get();

        foreach ($tickets as $ticket) {
            $ticket = $this->coreSeatNumberQtyTicket($ticket, $date);
        }
        return $tickets;
    }

    public function getRescheduleAvailableData(Request $req)
    {
        $date = null;
        $strDate = $req->visit_date ? $req->visit_date : 'now';
        try {
            $date = new DateTime($strDate, new DateTimeZone('Asia/Jakarta'));
            $now =  new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            if ($date->format('Y-m-d') < $now->format('Y-m-d')) {
                return response()->json(["error" => "Invalid selected date, selected visit date must be greather equal than now"], 403);
            }
        } catch (\Throwable $th) {
            return response()->json(["error" => "Invalid date format"], 403);
        }
        $ticket = Ticket::where('id', $req->ticket_id)->first();
        if (!$ticket) {
            return response()->json(["error" => "Ticket data not found"], 404);
        }
        $availableDays = $ticket->event()->first()->availableDays()->get();
        $ticket = $this->coreSeatNumberQtyTicket($ticket, $date);
        return response()->json(["ticket" => $ticket, "available_day" => $availableDays], 200);
    }

    public function getVisitDateAvlTicket(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "date" => "required|string",
            "ticket_id" => "required|string"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $date = null;
        try {
            $date = new DateTime($req->date, new DateTimeZone('Asia/Jakarta'));
        } catch (\Throwable $th) {
            return response()->json(["error" => "Invalid date format", "dates" => $req->dates], 403);
        }
        $ticket = Ticket::where('id', $req->ticket_id)->first();
        if (!$ticket) {
            return response()->json(["error" => "Ticket data not found"], 404);
        }
        $event = $ticket->event()->first();
        $quantity = $ticket->quantity;
        if ($event->category === "Attraction" || $event->category === "Daily Activities" || $event->category === "Tour Travel (recurring)") {
            $purchases = DB::table('purchases')
                ->join('daily_tickets', 'daily_tickets.purchase_id', '=', 'purchases.id')
                ->where('purchases.ticket_id', '=', $req->ticket_id)
                ->where('daily_tickets.visit_date', '=', $date->format('Y-m-d'))
                ->get();
            $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $avldt = $event->availableDays()->where('day', $date->format('D'))->first();
            if ($avldt) {
                $maxTime = new DateTime($avldt->max_limit_time, new DateTimeZone('Asia/Jakarta'));
                if ($now < $date || (
                    $now->format('Y-m-d') === $date->format('Y-m-d') && $now->format("H:i") <= $maxTime->format("H:i"))) {
                    $quantity = intval($ticket->limitDaily()->first()->limit_quantity) - count($purchases);
                } else {
                    $quantity = 0;
                }
            } else {
                $quantity = 0;
            }
        }

        return response()->json(["ticket_id" => $ticket->id, "quantity" => $quantity], 200);
    }

    public function getVisitDateAvlTickets(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "dates" => "required|array",
            "ticket_ids" => "required|array"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        if (count($req->dates) !== count($req->ticket_ids)) {
            return response()->json(["error" => "Length of dates array and ticket_ids not similiar"], 403);
        }
        $dates = [];
        foreach ($req->dates as $date) {
            try {
                $date = new DateTime($date, new DateTimeZone('Asia/Jakarta'));
                $dates[] = $date;
            } catch (\Throwable $th) {
                return response()->json(["error" => "Invalid date format", "dates" => $req->dates], 403);
            }
        }
        $returnData = [];
        $ticket = null;

        for ($i = 0; $i < count($dates); $i++) {
            $ticket = $ticket === null ? Ticket::where('id', $req->ticket_ids[$i])->first() : ($ticket->id === $req->ticket_ids[$i] ? $ticket : Ticket::where('id', $req->ticket_ids[$i])->first());

            if (!$ticket) {
                return response()->json(["error" => "Ticket data not found"], 404);
            }

            $event = $ticket->event()->first();
            $quantity = $ticket->quantity;
            if ($event->category === "Attraction" || $event->category === "Daily Activities" || $event->category === "Tour Travel (recurring)") {
                $purchases = DB::table('purchases')
                    ->join('daily_tickets', 'daily_tickets.purchase_id', '=', 'purchases.id')
                    ->where('purchases.ticket_id', '=', $req->ticket_ids[$i])
                    ->where('daily_tickets.visit_date', '=', $dates[$i]->format('Y-m-d'))
                    ->get();
                $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                $avldt = $event->availableDays()->where('day', $dates[$i]->format('D'))->first();
                if ($avldt) {
                    $maxTime = new DateTime($avldt->max_limit_time, new DateTimeZone('Asia/Jakarta'));
                    if ($now < $dates[$i] || (
                        $now->format('Y-m-d') === $dates[$i]->format('Y-m-d') && $now->format("H:i") <= $maxTime->format("H:i"))) {
                        $quantity = intval($ticket->limitDaily()->first()->limit_quantity) - count($purchases);
                    } else {
                        $quantity = 0;
                    }
                } else {
                    $quantity = 0;
                }
            }

            $returnData[] = ["ticket_id" => $ticket->id, "quantity" => $quantity,];
        }
        return response()->json(["tickets" => $returnData], 200);
    }

    private function getVouchers($eventId)
    {
        $vouchers = Voucher::where('event_id', $eventId)->get();
        foreach ($vouchers as $voucher) {
            $purchaseVc = [];
            foreach (Purchase::where('code', $voucher->code)->get() as $pch) {
                if($pch->payment()->first()->pay_state !== "EXPIRED"){
                    array_push($purchaseVc, $pch);
                }
            }
            $forTickets = $voucher->forTickets()->get();
            foreach ($forTickets as $forTicket) {
                $forTicket->ticket = $forTicket->ticket()->first();
            }
            $voucher->for_tickets = $forTickets;
            $voucher->avl_qty = floatval($voucher->quantity) - count($purchaseVc);
        }
        return $vouchers;
    }

    public function getAvailableSeatNumberDailyTicket(Request $req, $eventId)
    {
        $pchCtrl = new PchCtrl();
        $pchCtrl->loadTrxData();
        $event = null;
        if ($req->org) {
            $event = Event::where('id', $eventId)->where('is_publish', '<', 3)->where('deleted', 0)->first();
        } else {
            $event = Event::where('id', $eventId)->where('is_publish', 2)->where('deleted', 0)->first();
        }
        if (!$event) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        $event = $this->resetDateDailyType($event);
        if ($event->category != 'Attraction' && $event->category != 'Daily Activities' && $event->category != 'Tour Travel (recurring)') {
            return response()->json(["error" => "This event type is not a daily event / ticket"], 403);
        }
        $customFields = [];
        if ($event->custom_fields != null || $event->custom_fields != '') {
            $customFields = explode('|', $event->custom_fields);
        }
        $event->custom_fields = $customFields;
        $date = null;
        try {
            $date = new DateTime($req->visit_date, new DateTimeZone('Asia/Jakarta'));
        } catch (\Throwable $th) {
            return response()->json(["error" => "Invalid date format"], 403);
        }
        if ($date < new DateTime($event->end_date . ' ' . $event->end_time, new DateTimeZone('Asia/Jakarta'))) {
            $event->tickets = $this->getAvailableSeatNumberQty($event, $date);
        }
        $org = $event->org()->first();
        $org->legality = $org->credibilityData()->first();
        return response()->json([
            "event" => $event,
            "available_days" => $event->availableDays()->get(),
            "available_reschedule" => $event->availableReschedule()->first(),
            "breakdowns" => $event->breakdowns()->get(),
            "guest" => $event->guests()->get(),
            "sponsors" => $event->sponsors()->get(),
            "exhibitors" => $event->exhs()->get(),
            "handbooks" => $event->handbooks()->get(),
            "receptionists" => $event->receptionists()->get(),
            "organization" => $org,
            "vouchers" => $this->getVouchers($event->id),
        ], 200);
    }

    public function getById(Request $req, $eventId)
    {
        $pchCtrl = new PchCtrl();
        $pchCtrl->loadTrxData();
        $event = null;
        if ($req->org) {
            $event = Event::where('id', $req->event_id)->where('deleted', 0)->first();
        } else {
            $event = Event::where('id', $eventId)->where('is_publish', 2)->where('deleted', 0)->first();
        }
        if (!$event) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        $event = $this->resetDateDailyType($event);
        $customFields = [];
        if ($event->custom_fields != null || $event->custom_fields != '') {
            $customFields = explode('|', $event->custom_fields);
        }
        $event->custom_fields = $customFields;
        $date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        if ($date < new DateTime($event->end_date . ' ' . $event->end_time, new DateTimeZone('Asia/Jakarta'))) {
            $event->tickets = $this->getAvailableSeatNumberQty($event, $date);
        }
        // $event->tickets = $this->getAvailableSeatNumberQty($event, $date);
        $org = $event->org()->first();
        $org->legality = $org->credibilityData()->first();
        return response()->json([
            "event" => $event,
            "available_days" => $event->availableDays()->get(),
            "available_reschedule" => $event->availableReschedule()->first(),
            "breakdowns" => $event->breakdowns()->get(),
            "guest" => $event->guests()->get(),
            "sponsors" => $event->sponsors()->get(),
            "exhibitors" => $event->exhs()->get(),
            "handbooks" => $event->handbooks()->get(),
            "receptionists" => $event->receptionists()->get(),
            "organization" => $org,
            "vouchers" => $this->getVouchers($event->id),
        ], 200);
    }

    public function getBySlug(Request $req, $slug)
    {
        $pchCtrl = new PchCtrl();
        $pchCtrl->loadTrxData();
        $event = null;
        if ($req->org) {
            $event = Event::where('slug', $slug)->where('is_publish', '<', 3)->where('deleted', 0)->first();
        } else {
            $event = Event::where('slug', $slug)->where('is_publish', 2)->where('deleted', 0)->first();
        }
        if (!$event) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        $event = $this->resetDateDailyType($event);
        $customFields = [];
        if ($event->custom_fields != null || $event->custom_fields != '') {
            $customFields = explode('|', $event->custom_fields);
        }
        $event->custom_fields = $customFields;
        $date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        if ($date < new DateTime($event->end_date . ' ' . $event->end_time, new DateTimeZone('Asia/Jakarta'))) {
            $event->tickets = $this->getAvailableSeatNumberQty($event, $date);
        }
        $org = $event->org()->first();
        $org->legality = $org->credibilityData()->first();
        return response()->json([
            "event" => $event,
            "available_days" => $event->availableDays()->get(),
            "available_reschedule" => $event->availableReschedule()->first(),
            "breakdowns" => $event->breakdowns()->get(),
            "guest" => $event->guests()->get(),
            "sponsors" => $event->sponsors()->get(),
            "exhibitors" => $event->exhs()->get(),
            "handbooks" => $event->handbooks()->get(),
            "receptionists" => $event->receptionists()->get(),
            "organization" => $org,
            "vouchers" => $this->getVouchers($event->id),
        ], 200);
    }

    public function getByOrg(Request $req, $orgId)
    {
        $pchCtrl = new PchCtrl();
        $pchCtrl->loadTrxData();
        if (!Organization::where('id', $orgId)->first()) {
            return response()->json(["error" => "Organization data not found"], 404);
        }
        $events = null;
        if ($req->org) {
            $events = Event::where('org_id', $orgId)->where('deleted', 0)->get();
        } else {
            $events = Event::where('org_id', $orgId)->where('is_publish', 2)->where('deleted', 0)->get();
        }
        if (count($events) == 0) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        $data = ["events" => []];
        foreach ($events as $event) {
            $event = $this->resetDateDailyType($event);
            $customFields = [];
            if ($event->custom_fields != null || $event->custom_fields != '') {
                $customFields = explode('|', $event->custom_fields);
            }
            $event->custom_fields = $customFields;
            $date = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            if ($date < new DateTime($event->end_date . ' ' . $event->end_time, new DateTimeZone('Asia/Jakarta'))) {
                $event->tickets = $this->getAvailableSeatNumberQty($event, $date);
            }
            $org = $event->org()->first();
            $org->legality = $org->credibilityData()->first();
            $data["events"][] = [
                "event" => $event,
                "available_days" => $event->availableDays()->get(),
                "available_reschedule" => $event->availableReschedule()->first(),
                "breakdowns" => $event->breakdowns()->get(),
                "guest" => $event->guests()->get(),
                "sponsors" => $event->sponsors()->get(),
                "exhibitors" => $event->exhs()->get(),
                "handbooks" => $event->handbooks()->get(),
                "receptionists" => $event->receptionists()->get(),
                "organization" => $org,
                "vouchers" => $this->getVouchers($event->id),
            ];
        }
        return response()->json($data, 200);
    }

    public function mainDelete(Request $req, $orgId, $forAdmin=false)
    {
        $eventObj = Event::where('id', $req->event_id)->where('org_id', $orgId)->where('deleted', 0);
        if (!$eventObj->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        $fixPurchases = 0;
        foreach ($eventObj->first()->ticketsNonFilter()->get() as $ticket) {
            foreach ($ticket->purchases()->get() as $purchase) {
                if ($purchase->amount == 0 || $purchase->payment()->first()->pay_state != 'EXPIRED') {
                    $fixPurchases += 1;
                    break;
                }
            }
            if ($fixPurchases > 0) {
                break;
            }
        }
        $deleted = null;
        if ($fixPurchases == 0) {
            Storage::delete('public/event_banners/' . explode('/', $eventObj->first()->logo)[3]);
            $deleted = $eventObj->delete();
        } else {
            if ((new DateTime('now', new DateTimeZone('Asia/Jakarta')) < new DateTime($eventObj->first()->end_date . ' ' . $eventObj->first()->end_time, new DateTimeZone('Asia/Jakarta'))) &&
                // ($eventObj->first()->category != 'Attraction' && $eventObj->first()->category != 'Daily Activities' && $eventObj->first()->category != 'Tour Travel (recurring)') && 
                !$forAdmin
            ) {
                // if($eventObj->first()->is_publish == 1 || $eventObj->first()->is_publish == 2){
                return response()->json(["error" => "Operation not allowed to your event. Your event still active"], 402);
            }
            Ticket::where('event_id', $eventObj->first()->id)->update(["deleted" => 1]);
            $deleted = $eventObj->update(['deleted' => 1, "allow_refund" => 1]);
        }
        return response()->json(["deleted" => $deleted], 202);
    }

    public function delete(Request $req, $orgId){
        return $this->mainDelete($req, $orgId);
    }

    public function deleteForAdmin(Request $req, $orgId){
        return $this->mainDelete( $req, $orgId, true);
    }

    public function setPublishState(Request $req, $orgId)
    {
        // Event statuses
        // 1 => un-publish
        // 2 => published
        // 3 => ended
        // 4 => pending withdraw (first status code 1)
        // 5 => pending withdraw (first status code 2)
        $event = Event::where('id', $req->event_id)->where('org_id', $orgId)->where('deleted', 0);
        if (!$event->first()) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        if ($req->code_pub_state != 1 && $req->code_pub_state != 2) {
            return response()->json(["error" => "Your code publish state have not recognized"], 402);
        }
        if ($event->first()->is_publish == 0 || $event->first()->is_publish >= 3) {
            return response()->json(["error" => "Your event have not allowed to change the publish state"], 403);
        }
        // if (!Auth::user()->legality()->first()) {
        //     return response()->json(["error" => "Please fill your legality data first"], 403);
        // }
        // if (!$req->org->credibilityData()->first()) {
        //     return response()->json(["error" => "Please fill your legality data first"], 403);
        // }
        $updated = $event->update([
            "is_publish" => $req->code_pub_state
        ]);
        return response()->json(["updated" => $updated], 202);
    }

    public function getQREvent(Request $req, $orgId)
    {
        $event = Event::where('id', $req->event_id)->where('org_id', $orgId)->first();
        $org = $event->org()->first();
        $org->legality = $org->credibilityData()->first();
        $pdf = Pdf::loadView('pdfs.unique-qr-download', [
            'myData' => Auth::user(),
            'event' => $event,
            'urlEvent' => env('FRONTEND_URL') . '/self-checkin/' . $event->id,
            'organizationID' => $orgId,
            'organization' => $org,
            'isManageEvent' => 1,
        ])->setPaper('a4', 'portrait');
        return $pdf->download();
    }

    public function rollBackEvent(Request $req){
        $updated = Event::where('id', $req->event_id)->where('is_publish', '<', 3)->update([
            'deleted' => 0
        ]);
        return response()->json(["msg" => $updated === 0 ? "Data not found" : "Update data has succeeded"], $updated === 0 ? 404 : 202);
    }
}
