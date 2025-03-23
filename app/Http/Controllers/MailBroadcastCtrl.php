<?php

namespace App\Http\Controllers;

use App\Mail\MailAttandace;
use App\Models\MailAttandance;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailBroadcastCtrl extends Controller
{
    public function create(Request $req)
    {
        if (!$req->message) {
            return response()->json(["error" => "Field message is required"], 403);
        }
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $endDate = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
        if ($now > $endDate) {
            return response()->json(["error" => "End date of this event have reached"], 403);
        }
        foreach ($req->event->tickets()->get() as $ticket) {
            foreach ($ticket->purchases()->get()->groupBy('user_id') as $key => $purchase) {
                Mail::to($purchase[0]->user()->first()->email)->send(new MailAttandace($req->event->name, $req->message));
            }
        }
        $mail = MailAttandance::create([
            'event_id' => $req->event->id,
            'message' => $req->message
        ]);
        return response()->json(["message" => $mail], 201);
    }

    public function resendMail(Request $req)
    {
        $mailMessage = MailAttandance::where('id', $req->mail_id)->first();
        if (!$mailMessage) {
            return response()->json(["error" => "Message not found"], 404);
        }
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $endDate = new DateTime($req->event->end_date, new DateTimeZone('Asia/Jakarta'));
        if ($now > $endDate) {
            return response()->json(["error" => "End date of this event have reached"], 403);
        }
        foreach ($req->event->tickets()->get() as $ticket) {
            foreach ($ticket->purchases()->get()->groupBy('user_id') as $key => $purchase) {
                Mail::to($purchase[0]->user()->first()->email)->send(new MailAttandace($req->event->name, $mailMessage->message));
            }
        }
        return response()->json(["message" => "Mail have sent to users"], 200);
    }

    public function delete(Request $req)
    {
        $mailMessage = MailAttandance::where('id', $req->mail_id);
        if (!$mailMessage->first()) {
            return response()->json(["error" => "Message not found"], 404);
        }
        $deleted = $mailMessage->delete();
        return response()->json(["deleted" => $deleted], 202);
    }

    public function gets(Request $req)
    {
        return response()->json(["mails" => $req->event->mailAttandances()->get()], 200);
    }
}
