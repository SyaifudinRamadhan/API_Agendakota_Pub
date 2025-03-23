<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Event;

class EventData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $eventId = $request->route()->parameter('eventId');
        if(!$eventId){
            $eventId = $request->event_id;
        }
        $orgId = $request->route()->parameter('orgId');
        $isAdmin = $request->user ? $request->user->admin()->first() : null;
        $event = $isAdmin ? Event::where('id', $eventId)->where('org_id', $orgId)->first() : Event::where('id', $eventId)->where('org_id', $orgId)->where('deleted', 0)->first();
        if(!$event){
            return response()->json(["error" => "Event data not found"], 404);
        }
        $request->event = $event;
        return $next($request);
    }
}
