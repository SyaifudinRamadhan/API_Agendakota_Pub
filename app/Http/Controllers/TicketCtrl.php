<?php

namespace App\Http\Controllers;

use App\Models\DailyTicketLimit;
use App\Models\SecretInfoTicket;
use App\Models\Ticket;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TicketCtrl extends Controller
{
    public function create(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'name' => 'required|string',
            'desc' => 'required|string',
            'type_price' => 'required|string',
            'max_purchase' => 'required|numeric',
            'seat_map' => 'image|max:2048',
            'cover' => 'image|max:2048',
            // meet_link => 'required'  (for type webnar only)
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        if ($req->type_price != '1' && $req->type_price != '2' && $req->type_price != '3') {
            return response()->json(["error" => "Input type price code had not registered"], 404);
        }
        // Type price had divided by three type
        // => Free (1)
        // => Paid (2)
        // => pay as you like - min 10000 (3)
        if ($req->type_price == '2' && !$req->price) {
            return response()->json(["error" => 'If you have selected code 2 for the type price, please fill the currency field'], 403);
        }
        if ($req->type_price == '2' && intval($req->price) < 10000) {
            return response()->json(["error" => "Sorry. Minimal transaction is IDR Rp. 10.000,-"], 403);
        }
        if ($req->event->is_publish == 0 || $req->event->is_publish >= 3) {
            return response()->json(["error" => "You can't add ticket in in-active event"], 403);
        }
        $start = null;
        $end = null;
        if ($req->event->category === "Webinar" && !isset($req->meet_link)) {
            return response()->json(["error" => "Meet link is required for type webinar"], 403);
        }
        if ($req->event->category != 'Attraction' && $req->event->category != 'Daily Activities' && $req->event->category != 'Tour Travel (recurring)') {
            if (!$req->quantity || !$req->start_date || !$req->end_date) {
                return response()->json(["error" => "Filed quantity, start_date, and end_date is required for this category"], 403);
            }
            try {
                $start = new DateTime($req->start_date, new DateTimeZone('Asia/Jakarta'));
                $end = new DateTime($req->end_date, new DateTimeZone('Asia/Jakarta'));
            } catch (\Throwable $th) {
                return response()->json(["error" => "Invalid format input date or time"], 400);
            }

            if ($start > $end) {
                return response()->json(["error" => "Start date must be lower than end date or end date of the event"], 403);
            }

            $endEvent = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
            if ($start > $endEvent) {
                $start = new DateTime($req->event->start_date, new DateTimeZone('Asia/Jakarta'));
            }
            if ($end > $endEvent) {
                $end = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
            }
        } else {
            if (!$req->daily_limit_qty) {
                // return response()->json(["error" => "Ticket for daily event, must be have a limit ticket quantity per days"], 403);
                $req->daily_limit_qty = 0;
            }
            $req->quantity = -1;
            $start = new DateTime($req->event->start_date, new DateTimeZone('Asia/Jakarta'));
            $end = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
        }
        $seatMap = null;
        if ($req->enable_seat_number == true && $req->hasFile('seat_map')) {
            // $fileName = pathinfo($req->file('seat_map')->getClientOriginalName(), PATHINFO_FILENAME);
            $fileName = BasicFunctional::randomStr(5) . '_' . time() . $req->file('seat_map')->getClientOriginalExtension();
            $req->file('seat_map')->storeAs('public/seat_map_details', $fileName);
            $seatMap = '/storage/seat_map_details/' . $fileName;
        }
        $cover = '/storage/ticket_covers/default.png';
        if ($req->hasFile('cover')) {
            // $filename = pathinfo($req->file('cover')->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = BasicFunctional::randomStr(5) . '_' . time() . $req->file('cover')->getClientOriginalExtension();
            $req->file('cover')->storeAs('public/ticket_covers', $filename);
            $cover = '/storage/ticket_covers/' . $filename;
        }
        $ticket = Ticket::create([
            'event_id' => $req->event->id,
            'name' => $req->name,
            'cover' => $cover,
            'desc' => $req->desc,
            'type_price' => $req->type_price,
            'price' => $req->type_price == 2 ? abs($req->price) : 0,
            'quantity' => $req->quantity,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'seat_number' => $req->enable_seat_number == true ? true : false,
            'max_purchase' => abs($req->max_purchase),
            'seat_map' => $seatMap,
            'deleted' => 0,
        ]);
        if (($req->event->category == 'Attraction' || $req->event->category == 'Daily Activities' || $req->event->category == 'Tour Travel (recurring)')) {
            DailyTicketLimit::create([
                'ticket_id' => $ticket->id,
                'limit_quantity' => abs($req->daily_limit_qty),
            ]);
        }
        if ($req->event->category === "Webinar") {
            $secretInfo = SecretInfoTicket::create([
                'ticket_id' => $ticket->id,
                'meet_link' => $req->meet_link,
                'desc' => !isset($req->additional_desc) ? '' : $req->additional_desc,
            ]);
            $ticket->secretInfo = $secretInfo;
        }
        return response()->json(["ticket" => $ticket], 201);
    }

    public function bulkCreate(Request $req)
    {
        $validatorBasic = Validator::make($req->all(), [
            'ticket_datas' => 'required|array',
        ]);
        if ($validatorBasic->fails()) {
            return response()->json($validatorBasic->errors(), 403);
        }
        $starts = [];
        $ends = [];

        foreach ($req->ticket_datas as $key => $ticket_data) {
            $validator = Validator::make($ticket_data, [
                'name' => 'required|string',
                'desc' => 'required|string',
                'type_price' => 'required|string',
                'max_purchase' => 'required|numeric',
                'seat_map' => 'image|max:2048',
                'cover' => 'image|max:2048',
                // meet_link => 'required'  (for type webnar only)
            ]);
            $ticket_data = (object) $ticket_data;
            if ($validator->fails()) {
                return response()->json($validator->errors(), 403);
            }
            if ($ticket_data->type_price != '1' && $ticket_data->type_price != '2' && $ticket_data->type_price != '3') {
                return response()->json(["error" => "Input type price code had not registered"], 404);
            }
            $start = null;
            $end = null;
            if ($req->event->category === "Webinar" && !isset($ticket_data->meet_link)) {
                return response()->json(["error" => "Meet link is required for type webinar"], 403);
            }
            if ($req->event->category != 'Attraction' && $req->event->category != 'Daily Activities' && $req->event->category != 'Tour Travel (recurring)') {
                if (!isset($ticket_data->quantity) || !isset($ticket_data->start_date) || !isset($ticket_data->end_date)) {
                    return response()->json(["error" => "Filed quantity, start_date, and end_date is required for this category"], 403);
                }
                try {
                    $start = new DateTime($ticket_data->start_date, new DateTimeZone('Asia/Jakarta'));
                    $end = new DateTime($ticket_data->end_date, new DateTimeZone('Asia/Jakarta'));
                } catch (\Throwable $th) {
                    return response()->json(["error" => "Invalid format input date or time"], 400);
                }

                if ($start > $end) {
                    return response()->json(["error" => "Start date must be lower than end date or end date of the event"], 403);
                }

                $endEvent = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
                if ($start > $endEvent) {
                    $start = new DateTime($req->event->start_date, new DateTimeZone('Asia/Jakarta'));
                }
                if ($end > $endEvent) {
                    $end = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
                }
            } else {
                if (!isset($ticket_data->daily_limit_qty)) {
                    // return response()->json(["error" => "Ticket for daily event, must be have a limit ticket quantity per days"], 403);
                    $ticket_data->daily_limit_qty = 0;
                }
                $ticket_data->quantity = -1;
                $start = new DateTime($req->event->start_date, new DateTimeZone('Asia/Jakarta'));
                $end = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
            }
            $starts[$key] = $start;
            $ends[$key] = $end;
        }

        foreach ($req->ticket_datas as $key => $ticket_data) {
            $ticket_data = (object) $ticket_data;
            $seatMap = null;
            // return response()->json(["data" => $ticket_data->enable_seat_number == "false" ? false : true], 500);
            if ($ticket_data->enable_seat_number == true && isset($ticket_data->seat_map)) {
                // $fileName = pathinfo($ticket_data->seat_map->getClientOriginalName(), PATHINFO_FILENAME);
                $fileName = BasicFunctional::randomStr(5) . '_' . time() . $ticket_data->seat_map->getClientOriginalExtension();
                $ticket_data->seat_map->storeAs('public/seat_map_details', $fileName);
                $seatMap = '/storage/seat_map_details/' . $fileName;
            }
            $cover = '/storage/ticket_covers/default.png';
            if (isset($ticket_data->cover)) {
                // $filename = pathinfo($ticket_data->cover->getClientOriginalName(), PATHINFO_FILENAME);
                $filename = BasicFunctional::randomStr(5) . '_' . time() . $ticket_data->cover->getClientOriginalExtension();
                $ticket_data->cover->storeAs('public/ticket_covers', $filename);
                $cover = '/storage/ticket_covers/' . $filename;
            }
            $ticket = Ticket::create([
                'event_id' => $req->event->id,
                'name' => $ticket_data->name,
                'cover' => $cover,
                'desc' => $ticket_data->desc,
                'type_price' => $ticket_data->type_price,
                'price' => $ticket_data->type_price == 2 ? abs($ticket_data->price) : 0,
                'quantity' => $ticket_data->quantity,
                'start_date' => $starts[$key]->format('Y-m-d'),
                'end_date' => $ends[$key]->format('Y-m-d'),
                'seat_number' => $ticket_data->enable_seat_number == "true" ? true : false,
                'max_purchase' => abs($ticket_data->max_purchase),
                'seat_map' => $seatMap,
                'deleted' => 0,
            ]);
            if (($req->event->category == 'Attraction' || $req->event->category == 'Daily Activities' || $req->event->category == 'Tour Travel (recurring)')) {
                DailyTicketLimit::create([
                    'ticket_id' => $ticket->id,
                    'limit_quantity' => abs($ticket_data->daily_limit_qty),
                ]);
            }
            if ($req->event->category === "Webinar") {
                SecretInfoTicket::create([
                    'ticket_id' => $ticket->id,
                    'meet_link' => $ticket_data->meet_link,
                    'desc' => !isset($ticket_data->additional_desc) ? '' : $ticket_data->additional_desc,
                ]);
            }
        }
        return response()->json(["message" => "Tickets has been created"], 201);
    }

    public function update(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ticket_id' => 'required|string',
            'name' => 'required|string',
            'desc' => 'required|string',
            'type_price' => 'required|string',
            'max_purchase' => 'required|numeric',
            'seat_map' => 'image|max:2048',
            'cover' => 'image|max:2048',
            // meet_link => 'required'  (for type webnar only)
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        if ($req->type_price != '1' && $req->type_price != '2' && $req->type_price != '3') {
            return response()->json(["error" => "Input type price code had not registered"], 404);
        }
        // Type price had divided by three type
        // => Free (1)
        // => Paid (2)
        // => pay as you like - min 10000 (3)
        if ($req->type_price == '2' && !$req->price) {
            return response()->json(["error" => 'If you have selected code 2 for the type price, please fill the currency field'], 403);
        }
        if ($req->type_price == '2' && intval($req->price) < 10000) {
            return response()->json(["error" => "Sorry. Minimal transaction is IDR Rp. 10.000,-"], 403);
        }
        $start = null;
        $end = null;
        if ($req->event->category === "Webinar" && !isset($req->meet_link)) {
            return response()->json(["error" => "Meet link is required for type webinar"], 403);
        }
        if ($req->event->category != 'Attraction' && $req->event->category != 'Daily Activities' && $req->event->category != 'Tour Travel (recurring)') {
            if (!$req->quantity || !$req->start_date || !$req->end_date) {
                return response()->json(["error" => "Filed quntity, start_date, and end_date is required for this category"], 403);
            }
            try {
                $start = new DateTime($req->start_date, new DateTimeZone('Asia/Jakarta'));
                $end = new DateTime($req->end_date, new DateTimeZone('Asia/Jakarta'));
            } catch (\Throwable $th) {
                return response()->json(["error" => "Invalid format input date or time"], 400);
            }
            $endEvent = new DateTime($req->event->end_date . ' ' . $req->event->end_time, new DateTimeZone('Asia/Jakarta'));
            if ($start > $end || $start > $endEvent || $end > $endEvent) {
                return response()->json(["error" => "Start date must be lower than end date or end date of the event"], 403);
            }
        } else {
            if (!$req->daily_limit_qty) {
                // return response()->json(["error" => "Ticket for daily event, must be have a limit ticket quantity per days"], 403);
                $req->daily_limit_qty = 0;
            }
            $req->quantity = -1;
            $start = new DateTime($req->event->start_date, new DateTimeZone('Asia/Jakarta'));
            $end = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
        }
        $ticketObj = Ticket::where('id', $req->ticket_id)->where('event_id', $req->event->id)->where('deleted', 0);
        $ticket = $ticketObj->first();
        $seatMap = $ticket->seat_map;
        if ($req->enable_seat_number == true && $req->hasFile('seat_map')) {
            if ($seatMap != null || $seatMap != '') {
                Storage::delete('public/seat_map_details/' . explode('/', $seatMap)[3]);
            }
            // $fileName = pathinfo($req->file('seat_map')->getClientOriginalName(), PATHINFO_FILENAME);
            $fileName = BasicFunctional::randomStr(5) . '_' . time() . $req->file('seat_map')->getClientOriginalExtension();
            $req->file('seat_map')->storeAs('public/seat_map_details', $fileName);
            $seatMap = '/storage/seat_map_details/' . $fileName;
        } else if ($req->enable_seat_number == false && ($seatMap != null || $seatMap != '')) {
            Storage::delete('public/seat_map_details/' . explode('/', $seatMap)[3]);
            $seatMap = null;
        }
        $cover = $ticket->cover;
        if ($req->hasFile('cover')) {
            // $filename = pathinfo($req->file('cover')->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = BasicFunctional::randomStr(5) . '_' . time() . $req->file('cover')->getClientOriginalExtension();
            $req->file('cover')->storeAs('public/ticket_covers', $filename);
            $cover = '/storage/ticket_covers/' . $filename;
            if ($cover !== '/storage/ticket_covers/default.png') {
                Storage::delete('public/ticket_covers/' . explode('/', $ticket->cover)[3]);
            }
        }
        $updated = $ticketObj->update([
            'name' => $req->name,
            'cover' => $cover,
            'desc' => $req->desc,
            'type_price' => $req->type_price,
            'price' => $req->price ? abs($req->price) : 0,
            'quantity' => $req->quantity,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'seat_number' => $req->enable_seat_number == true ? true : false,
            'max_purchase' => abs($req->max_purchase),
            'seat_map' => $seatMap,
        ]);
        DailyTicketLimit::where('ticket_id', $req->ticket_id)->delete();
        if (($req->event->category == 'Attraction' || $req->event->category == 'Daily Activities' || $req->event->category == 'Tour Travel (recurring)') && $updated > 0) {
            DailyTicketLimit::create([
                'ticket_id' => $req->ticket_id,
                'limit_quantity' => abs($req->daily_limit_qty),
            ]);
        }
        $ticket = $ticketObj->first();
        if ($req->event->category === "Webinar") {
            SecretInfoTicket::where('ticket_id', $ticket->id)->delete();
            $secretInfo = SecretInfoTicket::create([
                'ticket_id' => $ticket->id,
                'meet_link' => $req->meet_link,
                'desc' => !isset($req->additional_desc) ? '' : $req->additional_desc,
            ]);
            $ticket->secretInfo = $secretInfo;
        }
        return response()->json(["updated" => $updated, "ticket" => $ticket], $updated == 0 ? 404 : 202);
    }

    public function delete(Request $req)
    {
        $ticket = Ticket::where('id', $req->ticket_id)->where('event_id', $req->event->id)->where('deleted', 0);
        if (!$ticket->first()) {
            return response()->json(["error" => "Ticket data not found"], 404);
        }
        $seatMap = $ticket->first()->seat_map;
        if (count($ticket->first()->purchases()->get()) <= 0) {
            if ($seatMap != null || $seatMap != '') {
                Storage::delete('public/seat_map_details/' . explode('/', $seatMap)[3]);
            }
            $deleted = $ticket->delete();
        } else {
            $isAdmin = $req->user ? $req->user->admin()->first() : null;
            if (
                $ticket->first()->type_price == 1 &&
                (new DateTime('now', new DateTimeZone('Asia/Jakarta')) < new DateTime($req->event->end_date . ' ' . $req->event->end_time, new DateTimeZone('Asia/Jakarta'))) &&
                ($req->event->category != 'Attraction' && $req->event->category != 'Daily Activities' && $req->event->category != 'Tour Travel (recurring)') &&
                !$isAdmin
            ) {
                return response()->json(["error" => "You can't remove this ticket. Because your event are in progress and this ticket have linked with selled active tickets"], 403);
            }

            if ($ticket->first()->type_price == 1) {
                $deleted = $ticket->update(["deleted" => 1]);
            } else {
                $fixPurchases = 0;
                foreach ($ticket->first()->purchases()->get() as $purchase) {
                    if ($purchase->payment()->first()->pay_state != 'EXPIRED') {
                        $fixPurchases += 1;
                        break;
                    }
                }
                if (
                    $fixPurchases > 0 &&
                    (new DateTime('now', new DateTimeZone('Asia/Jakarta')) < new DateTime($req->event->end_date . ' ' . $req->event->end_time, new DateTimeZone('Asia/Jakarta'))) &&
                    ($req->event->category != 'Attraction' && $req->event->category != 'Daily Activities' && $req->event->category != 'Tour Travel (recurring)') &&
                    !$isAdmin
                ) {
                    return response()->json(["error" => "You can't remove this ticket. Because your event are in progress and this ticket have linked with selled active tickets"], 403);
                }

                if ($fixPurchases > 0) {
                    $deleted = $ticket->update(["deleted" => 1]);
                } else {
                    if ($seatMap != null || $seatMap != '') {
                        Storage::delete('public/seat_map_details/' . explode('/', $seatMap)[3]);
                    }
                    $deleted = $ticket->delete();
                }
            }
        }
        return response()->json(["deleted" => $deleted], 202);
    }

    public function rollbackTicket(Request $req)
    {
        $isAdmin = $req->user ? $req->user->admin()->first() : null;
        if (!$isAdmin) {
            return response()->json(["error" => "Action not allowed for another admin"], 403);
        }
        $ticket = Ticket::where('id', $req->ticket_id);
        if (!$ticket->first()) {
            return response()->json(["error" => "Ticket not found !"], 404);
        }
        $ticket->update(["deleted" => 0]);
        return response()->json(["message" => "Rollback ticket success"], 202);
    }

    public function get(Request $req)
    {
        $ticket = Ticket::where('id', $req->ticket_id)->where('event_id', $req->event->id)->where('deleted', 0)->first();
        if (!$ticket) {
            return response()->json(["error" => "Ticket data not found"], 404);
        }
        if ($ticket->type_price != 1) {
            $purchases = [];
            foreach ($ticket->purchases()->get() as $purchase) {
                if ($purchase->payment()->first()->pay_state != 'EXPIRED') {
                    $purchases[] = $purchase;
                }
            }
            $ticket->purchases = $purchases;
        } else {
            $ticket->purchases = $ticket->purchases()->get();
        }
        if (($req->event->category == 'Attraction' || $req->event->category == 'Daily Activities' || $req->event->category == 'Tour Travel (recurring)')) {
            $ticket->limit_daily = $ticket->limitDaily()->first();
        }
        if ($req->event->category === "Webinar") {
            $ticket->secretInfo = $ticket->secretInfo()->first();
        }
        return response()->json(["ticket" => $ticket], 200);
    }

    public function getTicketsCore(Request $req, $forOrganizer = false, $eventId)
    {
        $tickets = null;
        if ($forOrganizer) {
            $tickets = Ticket::where('event_id', $req->event->id)->get();
        } else {
            $tickets = Ticket::where('event_id', $eventId)->where('deleted', 0)->get();
        }
        if (count($tickets) == 0) {
            return response()->json(["error" => "Tickets data not found for this event"], 404);
        }

        foreach ($tickets as $ticket) {
            $purchases = [];
            foreach ($ticket->purchases()->with(['user', 'payment', 'checkin', 'visitDate', 'seatNumber', 'orgInv'])->get() as $purchase) {
                // if ($ticket->type_price != 1) {
                //     if ($purchase->payment()->first()->pay_state != 'EXPIRED') {
                //         $purchase->user = $purchase->user()->first();
                //         $purchase->payment = $purchase->payment()->first();
                //         $purchase->checkin = $purchase->checkin()->first();
                //         $purchase->visitDate = $purchase->visitDate()->first();
                //         $purchase->seatNumber = $purchase->seatNumber()->first();
                //         $purchases[] = $purchase;
                //     }
                // } else {
                //     $purchase->user = $purchase->user()->first();
                //     $purchase->payment = $purchase->payment()->first();
                //     $purchase->checkin = $purchase->checkin()->first();
                //     $purchase->visitDate = $purchase->visitDate()->first();
                //     $purchase->seatNumber = $purchase->seatNumber()->first();
                //     $purchases[] = $purchase;
                // }
                $purchase->user = $purchase->user;
                $purchase->payment = $purchase->payment;
                $purchase->checkin = $purchase->checkin;
                $purchase->visitDate = $purchase->visitDate;
                $purchase->seatNumber = $purchase->seatNumber;
                $purchase->orgInv = $purchase->orgInv;
                $purchases[] = $purchase;
            }
            $ticket->purchases = $purchases;
            if (($req->event->category == 'Attraction' || $req->event->category == 'Daily Activities' || $req->event->category == 'Tour Travel (recurring)')) {
                $ticket->limit_daily = $ticket->limitDaily()->first();
            }
            if ($forOrganizer && $req->event->category === "Webinar") {
                $ticket->secretInfo = $ticket->secretInfo()->first();
            }
        }
        return response()->json(["tickets" => $tickets], 200);
    }

    public function getTickets(Request $req)
    {
        return $this->getTicketsCore($req, true, null);
    }

    public function getTicketsPublic(Request $req, $eventId)
    {
        return $this->getTicketsCore($req, false, $eventId);
    }
}
