<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\EventSession;

class EventSessionData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = $request->route()->parameter('sessionId');
        if (!$sessionId) {
            $sessionId = $request->session_id;
        }
        $session = EventSession::where('id', $sessionId)
            ->where('event_id', $request->route()->parameter('eventId'))
            ->first();
        if (!$session) {
            return response()->json(["error" => "Session data not found"], 404);
        }
        $request->evtSession = $session;
        return $next($request);
    }
}
