<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Voucher;
use App\Models\VoucherTiket;
use DateTime;
use DateTimeZone;
use Hamcrest\Arrays\IsArray;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoucherCtrl extends Controller
{
    private function avlQty($voucher)
    {
        $purchaseVc = [];
        foreach (Purchase::where('code', $voucher->code)->get() as $pch) {
            if($pch->payment()->first()->pay_state !== "EXPIRED"){
                array_push($purchaseVc, $pch);
            }
        }
        return floatval($voucher->quantity) - count($purchaseVc);
    }

    public function create(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "name" => 'required|string',
            "code" => "required|string|unique:vouchers",
            "discount" => "required|numeric",
            "quantity" => "required|numeric",
            "start" => "required|date",
            "end" => "required|date"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $start = new DateTime($req->start, new DateTimeZone('Asia/Jakarta'));
        $end = new DateTime($req->end, new DateTimeZone('Asia/Jakarta'));
        $endEvent = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
        if ($start > $end || $start > $endEvent || $end > $endEvent) {
            return response()->json(["error" => "Start time can't greater than end time"], 403);
        }
        $voucher = Voucher::create([
            'event_id' => $req->event->id,
            'name' => $req->name,
            'code' => $req->code,
            'discount' => abs($req->discount),
            'quantity' => abs($req->quantity),
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
        $forTickets = [];
        if($req->ticket_ids && is_array($req->ticket_ids)){
            foreach ($req->ticket_ids as $ticket_id) {
                $vcTicket = VoucherTiket::create([
                    'voucher_id' => $voucher->id,
                    'ticket_id' => $ticket_id
                ]);
                $vcTicket->ticket = $vcTicket->ticket()->first();
                array_push($forTickets, $vcTicket);
            }   
        }
        $voucher->for_tickets = $forTickets;
        $voucher->avl_qty = $this->avlQty($voucher);
        return response()->json(["voucher" => $voucher], 201);
    }

    public function update(Request $req)
    {
        $validator = Validator::make($req->all(), [

            "name" => 'required|string',
            "discount" => "required|numeric",
            "quantity" => "required|numeric",
            "start" => "required|date",
            "end" => "required|date"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $start = new DateTime($req->start, new DateTimeZone('Asia/Jakarta'));
        $end = new DateTime($req->end, new DateTimeZone('Asia/Jakarta'));
        $endEvent = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
        if ($start > $end || $start > $endEvent || $end > $endEvent) {
            return response()->json(["error" => "Start time can't greater than end time"], 403);
        }
        $voucher = Voucher::where('id', $req->voucher_id)->where('event_id', $req->event->id);
        if (!$voucher->first()) {
            return response()->json(["error" => "Voucher data not found"], 404);
        }
        $voucher->update([
            'name' => $req->name,
            'code' => $voucher->first()->code,
            'discount' => abs($req->discount),
            'quantity' => abs($req->quantity),
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);
        $forTickets = [];
        VoucherTiket::where('voucher_id', $req->voucher_id)->delete();
        if($req->ticket_ids && is_array($req->ticket_ids)){
            foreach ($req->ticket_ids as $ticket_id) {
                $vcTicket = VoucherTiket::create([
                    'voucher_id' => $req->voucher_id,
                    'ticket_id' => $ticket_id
                ]);
                $vcTicket->ticket = $vcTicket->ticket()->first();
                array_push($forTickets, $vcTicket);
            }   
        }
        $voucher = $voucher->first();
        $voucher->for_tickets = $forTickets;
        $voucher->avl_qty = $this->avlQty($voucher);
        return response()->json(["voucher" => $voucher], 200);
    }

    public function delete(Request $req)
    {
        $deleted = Voucher::where('id', $req->voucher_id)->where('event_id', $req->event->id)->delete();
        return response()->json(["deleted" => $deleted], $deleted == 0 ? 404 : 202);
    }

    public function get(Request $req)
    {
        $voucher = Voucher::where('id', $req->voucher_id)->first();
        if (!$voucher) {
            return response()->json(["error" => "Voucher not found"], 404);
        }
        $forTickets = $voucher->forTickets()->get();
        foreach ($forTickets as $forTicket) {
            $forTicket->ticket = $forTicket->ticket()->first();
        }
        $voucher->for_tickets = $forTickets;
        $voucher->avl_qty = $this->avlQty($voucher);
        return response()->json(["voucher" => $voucher], 200);
    }

    public function gets(Request $req)
    {
        $eventId = $req->event_id;
        $eventId == null ? $eventId = $req->event->id : null;
        $vouchers = Voucher::where('event_id', $eventId)->get();
        foreach ($vouchers as $voucher) {
            $forTickets = $voucher->forTickets()->get();
            foreach ($forTickets as $forTicket) {
                $forTicket->ticket = $forTicket->ticket()->first();
            }
            $voucher->for_tickets = $forTickets;
            $voucher->avl_qty = $this->avlQty($voucher);
        }
        return response()->json(["vouchers" => $vouchers], count($vouchers) == 0 ? 404 : 200);
    }
}
