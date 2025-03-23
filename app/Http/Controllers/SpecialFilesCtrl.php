<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SpecialFilesCtrl extends Controller
{
    public function AttachInvProtection(Request $req, $orgId, $eventId, $invId, $file)
    {
        $orgInv = DB::table('org_invitations')->
            select(DB::raw('org_invitations.id as id, org_invitations.pch_id as pch_id, org_invitations.email as email, org_invitations.wa_num as wa_num, org_invitations.name as name, org_invitations.trx_img as trx_img, purchases.ticket_id as ticket_id, tickets.event_id as event_id'
        ))->
            leftJoin('purchases', 'purchases.id', '=', 'org_invitations.pch_id')->
            leftJoin('tickets', 'tickets.id', '=', 'purchases.ticket_id')->
            where('org_invitations.id', '=', $invId)->
            first();

        if (!$orgInv) {
            return response()->json(["error" => "Invitation data not found", "invId" => $invId], 404);
        }
        if ($orgInv->event_id != $req->event->id) {
            return response()->json(["error" => "Event ID not match"], 404);
        }
        $originalFileName = explode('/', $orgInv->trx_img);
        if ($originalFileName[count($originalFileName) - 1] != $file) {
            return response()->json(["error" => "Attachment data not found"], 404);
        }
        try {
            $fileDownload = Storage::download('/private/inv_attchs/' . $originalFileName[count($originalFileName) - 1]);
        } catch (\Throwable $th) {
            return response()->json(["error" => "Attachment not found"], 404);
        }
        return $fileDownload;
    }
}
