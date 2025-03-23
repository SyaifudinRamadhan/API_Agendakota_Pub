<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;
use App\Models\Team;

class EventOrganizer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->route()->parameter('orgId');
        $org = Organization::where('id', $orgId)->where('deleted', 0)->first();

        if (!$org) {
            return response()->json(["error" => "Organization not found"], 404);
        } else if ($org->user_id != Auth::user()->id) {
            $team = Team::where('org_id', $org->id)->where('user_id', Auth::user()->id)->first();
            if (!$team) {
                return response()->json(["error" => "Access forbidden. You are not an organizer event"], 403);
            }
        }
        $request->org = $org;
        return $next($request);
    }
}
