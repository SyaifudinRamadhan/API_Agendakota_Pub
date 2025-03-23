<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Purchase;
use App\Models\Ticket;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CheckinCtrl extends Controller
{
    public function createByUser(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                "event_id" => "required|string",
            ]
        );
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $event = Event::where('id', $req->event_id)->first();
        if (!$event) {
            return response()->json(["error" => "Event By ID not found"], 404);
        }
        $purchases = Auth::user()->purchases()->where('created_at', '>=', $event->created_at)->where('is_mine', true)->get();
        $checkined = 0;
        $unPaid = 0;
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $startEvent = new DateTime($event->start_date . ' ' . $event->start_time, new DateTimeZone('Asia/Jakarta'));
        $startEvent->sub(new DateInterval('PT2H'));
        $eventEnd = new DateTime($event->end_date . ' ' . $event->end_time, new DateTimeZone('Asia/Jakarta'));
        foreach ($purchases as $purchase) {
            $skip = false;
            if ($event->category == 'Attraction' || $event->category == 'Daily Activities' || $event->category == 'Tour Travel (recurring)') {
                $visitDateObj = $purchase->visitDate()->first();
                if (!$visitDateObj) {
                    $skip = true;
                } else {
                    $visitDate = new DateTime($visitDateObj->visit_date, new DateTimeZone('Asia/Jakarta'));
                    if ($now->format('Y-m-d') != $visitDate->format('Y-m-d')) {
                        $skip = true;
                    }
                }
            }
            if (
                !$skip && (($event->category == 'Attraction' || $event->category == 'Daily Activities' || $event->category == 'Tour Travel (recurring)')
                    || ($now <= $eventEnd && $now >= $startEvent))
            ) {
                $checkin = Checkin::where('pch_id', $purchase->id)->first();
                if ($checkin) {
                    $checkined += 1;
                } else if ($purchase->ticket()->first()->event_id == $event->id && ($purchase->amount == 0 || $purchase->payment()->first()->pay_state == 'SUCCEEDED')) {
                    $checkin = Checkin::create(
                        [
                            'pch_id' => $purchase->id,
                            'event_id' => $event->id,
                            'status' => 1,
                        ]
                    );
                    return response()->json(
                        [
                            "checkin_on" => $checkin->created_at,
                            "event" => $event,
                            "user" => Auth::user(),
                            "purchase" => $purchase,
                            "ticket" => $purchase->ticket()->first(),
                        ],
                        201
                    );
                } else if ($purchase->ticket()->first()->event_id == $event->id) {
                    $unPaid += 1;
                }
            }
        }

        if ($unPaid > 0) {
            return response()->json(["error" => "You have purchased this event ticket, but you haven't yet procressed the transaction"], 403);
        }
        if ($checkined > 0) {
            return response()->json(["error" => "You have checkined to this event"], 403);
        }
        return response()->json(["error" => "You have not buy a ticket of this event or the ticket has expired"], 404);
    }

    public function createByOrg(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                "qr_str" => "required|string",
            ]
        );
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $qrStr = explode("*~^|-|^~*", $req->qr_str);
        if (count($qrStr) < 2) {
            Log::info("Error scan QR organizer. QR Code is : " . $req->qr_str);
            return response()->json(["error" => "Purchase data not found in this event"], 404);
        }
        $purchase = Purchase::where('id', $qrStr[0])->where('user_id', $qrStr[1])->first();
        if (!$purchase) {
            return response()->json(["error" => "Purchase data not found in this event"], 404);
        }
        if ($purchase->amount > 0 && $purchase->payment()->first()->pay_state != 'SUCCEEDED') {
            return response()->json(["error" => "Successfull transaction not found in this purchase"], 404);
        }
        if ($purchase->ticket()->first()->event()->first()->id !== $req->event->id) {
            return response()->json(["error" => "This purchased ticket event not match"], 404);
        }
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $startEvent = new DateTime($req->event->start_date . ' ' . $req->event->start_time, new DateTimeZone('Asia/Jakarta'));
        $startEvent->sub(new DateInterval('PT2H'));
        $endEvent = new DateTime($req->event->end_date . ' ' . $req->event->end_time, new DateTimeZone('Asia/Jakarta'));
        if ($req->event->category == 'Attraction' || $req->event->category == 'Daily Activities' || $req->event->category == 'Tour Travel (recurring)') {
            $visitDateObj = $purchase->visitDate()->first();
            if (!$visitDateObj) {
                return response()->json(["error" => "This ticket is not valid"], 403);
            }
            $visitDate = new DateTime($visitDateObj->visit_date, new DateTimeZone('Asia/Jakarta'));
            if ($now->format('Y-m-d') != $visitDate->format('Y-m-d')) {
                return response()->json(["error" => "Selected visit date is not match with now"], 403);
            }
        }
        if (($req->event->category != 'Attraction' && $req->event->category != 'Daily Activities' && $req->event->category != 'Tour Travel (recurring)')
            && ($now > $endEvent || $now < $startEvent)
        ) {
            return response()->json(["error" => $now > $endEvent ? "The ticket has expired" : "Checkin can only be done when the event has started"], 403);
        }
        if ($purchase->is_mine == 0) {
            Invitation::where('pch_id', $purchase->id)->delete();
            Purchase::where('id', $purchase->id)->update([
                'is_mine' => true,
            ]);
        }
        $checkin = Checkin::where('pch_id', $purchase->id)->first();
        if ($checkin) {
            return response()->json(["error" => "Your ticket has been checked"], 403);
            // return response()->json(
            //     [
            //         "checkin" => $checkin,
            //         "purchase" => $purchase,
            //         "user" => $purchase->user()->first(),
            //         "ticket" => $purchase->ticket()->first(),
            //         "event" => $req->event,
            //         "status" => 2,
            //     ],
            //     201
            // );
        }
        $checkin = Checkin::create(
            [
                'pch_id' => $purchase->id,
                'event_id' => $req->event->id,
                'status' => 1,
            ]
        );
        return response()->json(
            [
                "checkin" => $checkin,
                "purchase" => $purchase,
                "user" => $purchase->user()->first(),
                "ticket" => $purchase->ticket()->first(),
                "event" => $req->event,
                "status" => 1,
            ],
            201
        );
    }

    public function delete(Request $req)
    {
        $deleted = Checkin::where('id', $req->checkin_id)->where('event_id', $req->event->id)->delete();
        return response()->json(["deleted" => $deleted], $deleted == 1 ? 202 : 404);
    }

    public function get(Request $req)
    {
        $checkin = Checkin::where('id', $req->checkin_id)->first();
        if (!$checkin) {
            return response()->json(["error" => "Checkin data not found"], 404);
        }
        $checkin->purchase = $checkin->pch()->first();
        $checkin->purchase->user = $checkin->purchase->user()->first();
        $checkin->purchase->ticket = $checkin->purchase->ticket()->first();
        return response()->json(["data" => $checkin], 200);
    }

    public function checkins(Request $req)
    {
        $tickets = Ticket::where('event_id', $req->event->id)->where('deleted', 0)->get();
        if (count($tickets) == 0) {
            return response()->json(["error" => "Tickets data not found for this event"], 404);
        }
        $purchases = [];
        $checkined = [];
        $notCheckin = [];
        foreach ($tickets as $ticket) {
            foreach ($ticket->purchases()->get() as $purchase) {
                if ($ticket->type_price != 1) {
                    if ($purchase->payment()->first()->pay_state != 'EXPIRED') {
                        $purchase->user = $purchase->user()->first();
                        $purchase->payment = $purchase->payment()->first();
                        $purchase->checkin = $purchase->checkin()->first();
                        $purchase->visitDate = $purchase->visitDate()->first();
                        $purchase->seatNumber = $purchase->seatNumber()->first();
                        $purchase->orgInv = $purchase->orgInv()->first();
                        $purchases[] = $purchase;
                        if ($purchase->checkin != null) {
                            $checkined[] = $purchase;
                        } else {
                            $notCheckin[] = $purchase;
                        }
                    }
                } else {
                    $purchase->user = $purchase->user()->first();
                    $purchase->payment = $purchase->payment()->first();
                    $purchase->checkin = $purchase->checkin()->first();
                    $purchase->visitDate = $purchase->visitDate()->first();
                    $purchase->seatNumber = $purchase->seatNumber()->first();
                    $purchase->orgInv = $purchase->orgInv()->first();
                    $purchases[] = $purchase;
                    if ($purchase->checkin != null) {
                        $checkined[] = $purchase;
                    } else {
                        $notCheckin[] = $purchase;
                    }
                }
            }
        }
        return response()->json(
            [
                "checkined" => $checkined,
                "not_checkin" => $notCheckin,
                "all" => $purchases,
            ],
            200
        );
    }
}
