<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\EventSession;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Storage;

class EvtSessionCtrl extends Controller
{
    public function create(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "name" => "required|string",
            "start_date" => "required|date",
            "end_date" => "required|date",
            "start_time" => "required|string",
            "end_time" => "required|string",
            "desc" => "required|string",
            "cover" => "required|image|max:2048"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        try {
            $start = new DateTime($req->start_date . ' ' . $req->start_time, new DateTimeZone('Asia/Jakarta'));
            $end = new DateTime($req->end_date . ' ' . $req->end_time, new DateTimeZone('Asia/Jakarta'));
        } catch (\Throwable $th) {
            return response()->json(["error" => "Invalid format date and time. Use Y-m-d or d-m-Y format"], 403);
        }
        $startEvent = new DateTime($req->event->start_date . ' ' . $req->event->start_time, new DateTimeZone('Asia/Jakarta'));
        $endEvent = new DateTime($req->event->end_date . ' ' . $req->event->end_time, new DateTimeZone('Asia/Jakarta'));
        if ($start >= $end || $start < $startEvent || $start >= $endEvent || $end <= $startEvent || $end > $endEvent) {
            return response()->json(["error" => "Your selected date time is out from date time event range"], 403);
        }
        // $coverImage = pathinfo($req->file('cover')->getClientOriginalName(), PATHINFO_FILENAME);
        $coverImage = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('cover')->getClientOriginalExtension();
        $req->file('cover')->storeAs('public/session_covers', $coverImage);
        $coverImage = '/storage/session_covers/' . $coverImage;
        $session = EventSession::create([
            "event_id" => $req->event->id,
            "name" => $req->name,
            "start_date" => $start->format('Y-m-d'),
            "end_date" => $end->format('Y-m-d'),
            "start_time" => $start->format('H:i:s'),
            "end_time" => $end->format('H:i:s'),
            "desc" => $req->desc,
            "cover" => $coverImage
        ]);
        return response()->json(["session" => $session], 201);
    }

    public function update(Request $req)
    {
        $session = EventSession::where('id', $req->session_id)->where('event_id', $req->event->id);
        if (!$session->first()) {
            return response()->json(["error" => "Session data not found"], 404);
        }
        $validator = Validator::make($req->all(), [
            "name" => "required|string",
            "start_date" => "required|date",
            "end_date" => "required|date",
            "start_time" => "required|string",
            "end_time" => "required|string",
            "desc" => "required|string",
            "cover" => "image|max:2048"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        try {
            $start = new DateTime($req->start_date . ' ' . $req->start_time, new DateTimeZone('Asia/Jakarta'));
            $end = new DateTime($req->end_date . ' ' . $req->end_time, new DateTimeZone('Asia/Jakarta'));
        } catch (\Throwable $th) {
            return response()->json(["error" => "Invalid format date and time. Use Y-m-d or d-m-Y format"], 403);
        }
        $startEvent = new DateTime($req->event->start_date . ' ' . $req->event->start_time, new DateTimeZone('Asia/Jakarta'));
        $endEvent = new DateTime($req->event->end_date . ' ' . $req->event->end_time, new DateTimeZone('Asia/Jakarta'));
        if ($start >= $end || $start < $startEvent || $start >= $endEvent || $end <= $startEvent || $end > $endEvent) {
            return response()->json(["error" => "Your selected date time is out from date time event range"], 403);
        }
        $coverImage = $session->first()->cover;
        if ($req->hasFile('cover')) {
            Storage::delete('public/session_covers/' . explode('/', $coverImage)[3]);
            // $coverImage = pathinfo($req->file('cover')->getClientOriginalName(), PATHINFO_FILENAME);
            $coverImage = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('cover')->getClientOriginalExtension();
            $req->file('cover')->storeAs('public/session_covers', $coverImage);
            $coverImage = '/storage/session_covers/' . $coverImage;
        }
        $updated = $session->update([
            "name" => $req->name,
            "start_date" => $start->format('Y-m-d'),
            "end_date" => $end->format('Y-m-d'),
            "start_time" => $start->format('H:i:s'),
            "end_time" => $end->format('H:i:s'),
            "desc" => $req->desc,
            "cover" => $coverImage
        ]);
        return response()->json(["updated" => $updated], 202);
    }

    public function delete(Request $req)
    {
        $deleted = EventSession::where('id', $req->session_id)->where('event_id', $req->event->id)->delete();
        return response()->json(["deleted" => $deleted], $deleted == 0 ? 404 : 202);
    }

    public function get(Request $req)
    {
        $eventSession = EventSession::where('id', $req->session_id)->where('event_id', $req->event->id)->first();
        if (!$eventSession) {
            return response()->json(["error" => "Event session not found"], 404);
        }
        return response()->json(["event_session" => $eventSession], 200);
    }

    public function getSessions(Request $req)
    {
        $eventSessions = EventSession::where('event_id', $req->event->id)->orderBy('start_date', 'ASC')->get();
        if (count($eventSessions) == 0) {
            return response()->json(["error" => "Event session not found"], 404);
        }
        return response()->json(["event_sessions" => $eventSessions], 200);
    }
}
