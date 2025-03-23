<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\ViralCity;
use DateTime;
use DateTimeZone;
use Hamcrest\Arrays\IsArray;
use Illuminate\Http\Request;

class SearchCtrl extends Controller
{
    private function countPurchases($event)
    {
        $total = 0;
        foreach ($event->tickets as $ticket) {
            $total += count($ticket->purchases);
        }
        return $total;
    }

    private function basePopEvents($events)
    {
        $fixEvents = [];
        for ($i = 0; $i < count($events); $i++) {
            $selectedIndex = $i;
            $selectedValue = $this->countPurchases($events[$selectedIndex]);
            for ($j = $i + 1; $j < count($events); $j++) {
                $toCompare = $this->countPurchases($events[$j]);
                if ($selectedValue < $toCompare) {
                    $selectedValue = $toCompare;
                    $selectedIndex = $j;
                }
            }
            if ($selectedIndex != $i) {
                $tmp = $events[$i];
                $events[$i] = $events[$selectedIndex];
                $events[$selectedIndex] = $tmp;
                $tmp = null;
            }
            
            // $events[$i]->org = $events[$i]->org()->first();
            if($events[$i]->org && $events[$i]->deleted === 0 && new DateTime($events[$i]->end_date . " " . $events[$i]->end_time, new DateTimeZone('Asia/Jakarta')) >= new DateTime('now', new DateTimeZone('Asia/Jakarta'))){
                // $events[$i]->available_days = $events[$i]->availableDays()->get();
                // $events[$i]->org->legality = $events[$i]->org->credibilityData()->first();
                // $events[$i]->tickets = $events[$i]->tickets()->orderBy('price', 'ASC')->get();
                array_push($fixEvents, $events[$i]);
            }
        }
        return $fixEvents;
    }

    public function popularEvents()
    {
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $events = Event::where('end_date', '>=', $now->format('Y-m-d'))->where('is_publish', 2)->where('visibility', true)->with([
            'org', 
            'availableDays',
            'org.legality',
            'tickets',
            'tickets.purchases'
        ])->get();
        return response()->json(["events" => $this->basePopEvents($events)], 200);
    }

    public function popularCityEvents()
    {
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $city = ViralCity::first()->city()->first();
        $events = Event::where('end_date', '>=', $now->format('Y-m-d'))->where('city', 'like', '%' . $city->name . '%')->where('is_publish', 2)->where('visibility', true)->with([
            'org', 
            'availableDays',
            'org.legality',
            'tickets',
            'tickets.purchases'
        ])->get();
        return response()->json(["city" => $city, "events" => $this->basePopEvents($events)], 200);
    }

    public function searchEvents(Request $req)
    {
        $whereClauses = [];
        if (!$req->include_all) {
            $whereClauses[] = [
                'is_publish', '=', 2,
            ];
        }
        if ($req->event_name) {
            $whereClauses[] = [
                'name', 'like', '%' . $req->event_name . '%'
            ];
        }
        // if ($req->until_date && $req->start_date && ($req->category != 'Attraction' && $req->category != 'Daily Activities' && $req->category != 'Tour Travel (recurring)')) {
        //     $end = new DateTime($req->until_date, new DateTimeZone('Asia/Jakarta'));
        //     $whereClauses[] = [
        //         'end_date', '<=', $end->format('Y-m-d')
        //     ];
        // }
        $start = new DateTime($req->start_date, new DateTimeZone('Asia/Jakarta'));
        if (!$req->include_all) {
            if ($req->start_date) {
                $whereClauses[] = [
                    'end_date', '>=', $start->format('Y-m-d')
                ];
            } else {
                $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                $whereClauses[] = [
                    'end_date', '>=', $now->format('Y-m-d')
                ];
            }
        }
        if ($req->city) {
            $whereClauses[] = [
                'city', 'like', '%' . $req->city . '%'
            ];
        }
        if ($req->exe_type) {
            $whereClauses[] = [
                'exe_type', '=', $req->exe_type
            ];
        }

        $whereClauses[] = [
            'visibility', '=', true
        ];
        $events = [];
        $untilDate = $req->until_date ? new DateTime($req->until_date, new DateTimeZone('Asia/Jakarta')) : null;

        foreach ($req->category ? Event::where($whereClauses)->whereIn('category', is_array($req->category) ? $req->category : [$req->category])->get() : Event::where($whereClauses)->with([
            'org', 
            'availableDays',
            'org.legality',
            'tickets',
            'tickets.purchases'
        ])->get() as $event) {
            $state = true;
            $trueTopic = true;
            $trueTime = true;
            if ($req->org_name && !str_contains($event->org()->first()->name, $req->org_name)) {
                $state = false;
            }
            if ($req->org_id && $req->org_id !== $event->org()->first()->id) {
                $state = false;
            }
            // if ($req->start_price != null && count($event->tickets()->where('price', '>=', intval($req->start_price))->get()) == 0) {
            //     $state = false;
            // }
            // if ($req->until_price != null && count($event->tickets()->where('price', '<=', intval($req->until_price))->get()) == 0) {
            //     $state = false;
            // }
            
            if($req->start_price != null && $req->start_price == 0 && $req->until_price != null && $req->until_price == 0){
                // for free tickets
                if(count($event->tickets()->where('type_price', 1)->get()) === 0){
                    $state = false;
                }
            }else if($req->start_price != null && $req->until_price != null){
                // for custom interval price
                if(count($event->tickets()->where('price', '>=', intval($req->start_price))->where('price', '<=', intval($req->until_price))->get()) == 0){
                    $state = false;
                }
            }else if($req->start_price != null){
                // for custom price with only start param
                if(count($event->tickets()->where('price', '>=', intval($req->start_price))->get()) == 0){
                    $state = false;
                }
            }else if($req->until_price != null){
                // for custom price with only max limit param
                if(count($event->tickets()->where('price', '<=', intval($req->until_price))->get()) == 0){
                    $state = false;
                }
            }


            if ($req->topic && $req->topic_delimiter && is_string($req->topic_delimiter)) {
                $topics = is_array($req->topic) ? $req->topic : [$req->topic];
                $eventTopic = explode($req->topic_delimiter, $event->topics);
                $trueTopic = false;
                foreach ($topics as $topic) {
                    if (in_array($topic, $eventTopic)) {
                        $trueTopic = true;
                        break;
                    }
                }
            }
            /* 
            Accept time condition:
                -> if interval => start_event <= until_date (user insert) && end_event >= start_date (user_insert)
                -> if only start => end_event >= start_date (user_insert)

            In invert condition:
                -> if interval => start_event > until_date (user insert) || end_event < start_date (user insert)
                -> if only start => end_event < start_date (user insert)
            */
            if (
                $untilDate && !$req->include_all &&
                ($event->category !== "Attraction" && $event->category !== "Tour Travel (recurring)" && $event->category !== "Daily Activities") &&
                (new DateTime($event->start_date, new DateTimeZone('Asia/Jakarta')) > $untilDate || new DateTime($event->end_date, new DateTimeZone('Asia/Jakarta')) < $start)
            ) {
                $trueTime = false;
            }
            if ($state && $trueTopic && $trueTime) {
                $events[] = $event;
            }
        }
        return response()->json(["events" => $this->basePopEvents($events), "until_p" => $req->until_price, "start__p" => $req->start_price], 200);
    }
}
