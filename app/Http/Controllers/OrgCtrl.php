<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\User;
use App\Models\Team;
use App\Models\Event;
use App\Models\Ticket;
use App\Mail\InviteTeam;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use DateTime;
use DateTimeZone;

class OrgCtrl extends Controller
{
    // Create organization (can access admin and user basic)
    public function create(Request $req, $userId = null)
    {
        if ($userId == null) {
            $userId = Auth::user()->id;
        } else {
            if (!User::where('id', $userId)->first()) {
                return response()->json(["error" => "User not found"], 404);
            }
        }
        $validator = Validator::make($req->all(), [
            'type' => 'required|string',
            'name' => 'required|string',
            'interest' => 'required|string',
            'desc' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $org = Organization::create([
            'user_id' => $userId,
            'type' => $req->type,
            'name' => $req->name,
            'slug' => Str::slug($req->name),
            'interest' => $req->interest,
            'desc' => $req->desc,
            'photo' => '/storage/org_avatars/default.png',
            'banner' => '/storage/org_banners/default.png',
            'email' => '-',
            'linkedin' => '-',
            'instagram' => '-',
            'twitter' => '-',
            'whatsapp' => '-',
            'website' => '-',
            'phone' => '-',
            'deleted' => 0,
        ]);
        return response()->json(["data" => $org], 201);
    }

    // Update profile organization (can access admin and user basic)
    public function update(Request $req, $isAdmin = null)
    {
        $validator = Validator::make($req->all(), [
            'org_id' => 'required|string',
            'type' => 'required|string',
            'name' => 'required|string',
            'photo' => 'image|max:2048',
            'banner' => 'image|max:3072',
            'interest' => 'required|string',
            'email' => 'required|string',
            'linkedin' => 'string',
            'instagram' => 'required|string',
            'twitter' => 'string',
            'whatsapp' => 'required|string',
            'website' => 'string',
            'desc' => 'required|string',
            'phone' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $orgObj = Organization::where('id', $req->org_id);
        if (!$orgObj->first()) {
            return response()->json(["error" => "Data not found or match"], 404);
        }
        if ($isAdmin == null) {
            $user = Auth::user();
            if ($orgObj->first()->user_id != $user->id) {
                return response()->json(["error" => "Data not found or match"], 404);
            }
        }
        // handle upload image
        $namePhoto = $orgObj->first()->photo;
        if ($req->hasFile('photo')) {
            // $originName = pathinfo($req->file('photo')->getClientOriginalName(), PATHINFO_FILENAME);
            $namePhoto = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('photo')->getClientOriginalExtension();
            $req->file('photo')->storeAs('public/org_avatars', $namePhoto);
            $namePhoto = '/storage/org_avatars/' . $namePhoto;
            if ($orgObj->first()->photo != '/storage/org_avatars/default.png') {
                $fileName = explode('/', $orgObj->first()->photo);
                Storage::delete('public/org_avatars/' . $fileName[3]);
            }
        }
        $nameBanner = $orgObj->first()->banner;
        if ($req->hasFile('banner')) {
            // $originalName = pathinfo($req->file('banner')->getClientOriginalName(), PATHINFO_FILENAME);
            $nameBanner = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('banner')->getClientOriginalExtension();
            $req->file('banner')->storeAs('public/org_banners', $nameBanner);
            $nameBanner = '/storage/org_banners/' . $nameBanner;
            if ($orgObj->first()->banner != '/storage/org_banners/default.png') {
                $fileName = explode('/', $orgObj->first()->banner);
                Storage::delete('public/org_banners/' . $fileName[3]);
            }
        }
        $updated = $orgObj->update([
            'type' => $req->type,
            'name' => $req->name,
            'slug' => Str::slug($req->name),
            'interest' => $req->interest,
            'desc' => $req->desc,
            'photo' => $namePhoto,
            'banner' => $nameBanner,
            'email' => $req->email,
            'linkedin' => $req->linkedin,
            'instagram' => $req->instagram,
            'twitter' => $req->twitter,
            'whatsapp' => $req->whatsapp,
            'website' => $req->website,
            'phone' => $req->phone,
            'deleted' => 0,
        ]);
        return response()->json(["updated" => $updated], $updated == 0 ? 404 : 202);
    }

    // Delete organiztion (can access admin only)
    private function partDelete($event_id, $orgId)
    {
        $eventObj = Event::where('id', $event_id)->where('org_id', $orgId);
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
        if ($fixPurchases == 0) {
            Storage::delete('public/event_banners/' . explode('/', $eventObj->first()->logo)[3]);
            $eventObj->delete();
        } else {
            Ticket::where('event_id', $eventObj->first()->id)->update(["deleted" => 1]);
            $eventObj->update(['deleted' => 1]);
        }
    }

    public function delete(Request $req, $isAdmin = null)
    {
        $validator = Validator::make($req->all(), ["org_id" => 'required']);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $orgObj = Organization::where('id', $req->org_id);
        if (!$orgObj->first()) {
            return response()->json(["error" => "Data not found or match " . $req->org_id], 404);
        }
        if ($isAdmin == null) {
            $user = Auth::user();
            if ($orgObj->first()->user_id != $user->id) {
                return response()->json(["error" => "Data not found or match " . $req->org_id], 404);
            }
        }
        $fixPurchaseActiveEvent = 0;
        $fixPurchaseInActiveEvent = 0;
        foreach ($orgObj->first()->eventsNoFilter()->get() as $event) {
            foreach ($event->ticketsNonFilter()->get() as $ticket) {
                foreach ($ticket->purchases()->get() as $purchase) {
                    // if (new DateTime('now', new DateTimeZone('Asia/Jakarta')) < new DateTime($event->end_date . ' ' . $event->end_time, new DateTimeZone('Asia/Jakarta')) && $event->deleted == 0) {
                    //     // if(($event->is_publish == 1 || $event->is_publish == 2) && ($purchase->amount == 0 || $purchase->payment()->first()->pay_state != 'EXPIRED')){
                    //     $fixPurchaseActiveEvent += 1;
                    //     break;
                    // } else if ($purchase->amount == 0 || $purchase->payment()->first()->pay_state != 'EXPIRED') {
                    //     $fixPurchaseInActiveEvent += 1;
                    // }
                    if ($purchase->amount == 0 || $purchase->payment()->first()->pay_state != 'EXPIRED') {
                        $fixPurchaseInActiveEvent += 1;
                    }
                }
                // if ($fixPurchaseActiveEvent > 0) {
                //     break;
                // }
                if ($fixPurchaseInActiveEvent > 0) {
                    break;
                }
            }
            // if ($fixPurchaseActiveEvent > 0) {
            //     break;
            // }
            if ($fixPurchaseInActiveEvent > 0) {
                break;
            }
        }
        // if ($fixPurchaseActiveEvent > 0) {
        //     return response()->json(["error" => "You can't remove this organization, because this organization have active events"], 403);
        // }
        $deleted = null;
        if ($fixPurchaseInActiveEvent > 0) {
            $deleted = $orgObj->update(['deleted' => '1']);
            $orgId = $orgObj->first()->id;
            foreach ($orgObj->first()->eventsNoFilter()->get() as $event){
                $this->partDelete($event->id, $orgId);
            }
        } else {
            foreach ($orgObj->first()->eventsNoFilter()->get() as $event) {
                Storage::delete('public/event_banners/' . explode('/', $event->logo)[3]);
            }
            if (explode('/', $orgObj->first()->photo)[3] !== "default.png") {
                Storage::delete('public/org_avatars/' . explode('/', $orgObj->first()->photo)[3]);
            }
            if (explode('/', $orgObj->first()->banner)[3] !== "default.png") {
                Storage::delete('public/org_banners/' . explode('/', $orgObj->first()->banner)[3]);
            }

            $deleted = $orgObj->delete();
        }
        return response()->json(['deleted' => $deleted], 202);
    }

    // Read organization (can access admin and user basic)
    public function getOrg($orgId)
    {
        $org = Organization::where('id', $orgId)->where('deleted', 0)->with('user')->first();
        if (!$org) {
            return response()->json(["error" => "Data not found or match"], 404);
        }
        $org->legality = $org->credibilityData()->first();
        return response()->json(["organization" => $org], 200);
    }

    public function getOrgsByUser($userId = null)
    {
        if ($userId == null) {
            $userId = Auth::user()->id;
        }
        $orgs = Organization::where('user_id', $userId)->where('deleted', 0)->get();
        $teams = Team::where('user_id', $userId)->get();
        foreach ($orgs as $org) {
            $org->legality = $org->credibilityData()->first();
        }
        foreach ($teams as $team) {
            $org = $team->organization()->first();
            $org->is_team = true;
            $org->legality = $org->credibilityData()->first();
            $orgs[] = $org;
        }
        return response()->json(["organizations" => $orgs], count($orgs) == 0 ? 404 : 200);
    }

    // Create teams (admin and user basic)
    public function inviteTeam(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "email" => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $user = Auth::user();
        if ($req->email === $user->email) {
            return response()->json(["error" => "Please input different event of your account"], 403);
        }
        $org = Organization::where('id', $req->org_id)->where('deleted', 0)->first();
        if (!$org || $user->id != $org->user_id) {
            return response()->json(["error" => "Data not found or match"], 404);
        }
        $targetUser = User::where('email', $req->email)->first();
        if ($targetUser) {
            if (Team::where('org_id', $org->id)->where('user_id', $targetUser->id)->first()) {
                return response()->json(["error" => "Please input different event of your registered member"], 403);
            }
        }
        $defaultPassword = 'team_' . $org->slug;
        $token = JWT::encode([
            "email" => $req->email,
            "org_id" => $org->id,
            "def_pass" => $defaultPassword
        ], env('JWT_SECRET'), env('JWT_ALG'));
        // send email to target
        Mail::to($req->email)->send(new InviteTeam($req->email, $token, $org->name, $defaultPassword));
        return response()->json(["message" => "Invitation has been sent"], 202);
    }

    // Receive / accept invitation
    public function acceptInviteTeam($token)
    {
        $decoded = '';
        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), env('JWT_ALG')));
        } catch (\Throwable $th) {
            return response()->json(["error" => "Signature token is not valid"], 403);
        }
        // check email is registered ? if isn't system will be automatic registered this with default password
        $user = User::where('email', $decoded->email)->first();
        if (!$user) {
            $mailSplit = explode("@", $decoded->email);
            $user = User::create([
                'f_name' => $mailSplit[0],
                'l_name' => $mailSplit[0],
                'name' => $mailSplit[0],
                'email' => $decoded->email,
                'password' => $decoded->def_pass,
                'g_id' => '-',
                'photo' => '/storage/avatars/default.png',
                'is_active' => '1',
                'phone' => '-',
                'linkedin' => '-',
                'instagram' => '-',
                'twitter' => '-',
                'whatsapp' => '-',
                "deleted" => 0
            ]);
        } else if (
            Team::where('org_id', $decoded->org_id)->where('user_id', $user->id)->first()
        ) {
            return redirect()->to(env("FRONTEND_URL") . '/auth-user?redirect_to=invite_team&org_id=' . $decoded->org_id);
        }
        Team::create([
            'org_id' => $decoded->org_id,
            'user_id' => $user->id
        ]);
        // NOTE : replace with return redirect to react App
        return redirect()->to(env("FRONTEND_URL") . '/auth-user?redirect_to=invite_team&org_id=' . $decoded->org_id);
    }

    // Get teams
    public function getTeams(Request $req)
    {
        $org = Organization::where('id', $req->org_id)->where('deleted', 0)->first();
        if (!$org || $org->user_id != Auth::user()->id) {
            return response()->json(["error" => "Data not found or not match"], 404);
        }
        $teams = Team::where('org_id', $org->id)->get();
        foreach ($teams as $team) {
            $team->user = $team->user()->first();
            $team->organization = $team->organization()->first();
        }
        return response()->json(["teams" => $teams], count($teams) == 0 ? 404 : 200);
    }

    // Delete team (admin and user basic)
    public function deleteTeam(Request $req)
    {
        $team = Team::where('id', $req->team_id);
        $user = Auth::user();
        if (!$team->first()) {
            return response()->json(["error" => "Team not found"], 404);
        }
        $org = Organization::where('id', $team->first()->org_id)->where('deleted', 0)->first();
        if ($user->id != $org->user_id) {
            return response()->json(["error" => "you are isn't an organizer, but only a team member of this organization"], 403);
        }
        $deleted = $team->delete();
        return response()->json(["deleted" => $deleted], 202);
    }
}
