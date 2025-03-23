<?php

namespace App\Http\Controllers;

use App\Mail\AdminWdNotification;
use App\Mail\VerificationBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;
use App\Models\Withdraw;
use App\Models\Event;
use App\Models\Purchase;
use App\Models\BillAccount;
use App\Models\DisburstmentWd;
use App\Models\Otp;
use App\Models\ProfitSetting;
use App\Models\RefundData;
use App\Models\Ticket;
use DateTime;
use DateTimeZone;
use DateInterval;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Xendit\Disbursements;
use Illuminate\Support\Str;

class WithdrawCtrl extends Controller
{
    //management bill account
    public function getBanksCode()
    {
        return response()->json(["banks" => config('banks')], 200);
    }

    public function createAccount(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'bank_name' => 'required|string',
            'acc_number' => 'required|numeric',
            'acc_name' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        if (!array_key_exists($req->bank_name, config('banks'))) {
            return response()->json(["error" => "Bank not available"], 404);
        };
        if (count(BillAccount::where('org_id', $req->org->id)->where('status', 0)->get()) > 0) {
            return response()->json(["error" => "Tou have un-verified account. Please verify it first !!!"], 406);
        }
        $otp = Str::password(8, true, true, false);
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $data = BillAccount::create([
            'org_id' => $req->org->id,
            'bank_name' => $req->bank_name,
            'acc_name' => $req->acc_name,
            'acc_number' => $req->acc_number,
            'status' => 0,
            'otp' => $otp,
            'exp_to_verify' => $now->add(new DateInterval('PT2M')),
            'deleted' => 0
        ]);
        $mail_status = true;
        $email = Auth::user()->email;
        try {
            Mail::to($email)->send(new VerificationBank($data->bank_name, $data->acc_number, $otp));
        } catch (\Throwable $th) {
            ResendTrxNotification::writeErrorLog('App\Mail\VerificationBank', "bank verification", [$data->bank_name, $data->acc_number, $otp], $email);
            $mail_status = false;
        }
        $data->icon = '/storage/images/bank-icons/' . $data->bank_name . '.png';
        $data->bank_name = config('banks')[$data->bank_name];
        return response()->json([
            "data" => $data,
            "message" => "check your email to get the OTP code",
            "mail_status" => $mail_status
        ], 201);
    }

    public function deleteAccount(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "bank_acc_id" => "required|string"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $bankAcc = BillAccount::where('id', $req->bank_acc_id)->where('org_id', $req->org->id);
        if (!$bankAcc->first()) {
            return response()->json(["error" => "Bank account data not found"], 404);
        }
        $deleted = 0;
        if (count($bankAcc->first()->withdraws()->get()) > 0) {
            $deleted = $bankAcc->update(['deleted' => 0]);
        } else {
            $deleted = $bankAcc->delete();
        }
        return response()->json(["deleted" => $deleted], 202);
    }

    public function verifyAccount(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'bank_acc_id' => 'required|string',
            'otp' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $bankAcc = BillAccount::where('id', $req->bank_acc_id)->where('org_id', $req->org->id);
        if (!$bankAcc->first()) {
            return response()->json(["error" => "Bank account data not found"], 404);
        }
        if ($bankAcc->first()->otp != $req->otp) {
            return response()->json(["error" => "OTP code is not valid"], 403);
        }
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $exp = new DateTime($bankAcc->first()->exp_to_verify, new DateTimeZone('Asia/Jakarta'));
        if($now > $exp){
            $otp = Str::password(8, true, true, false);
            $bankAcc->update([
                "otp" => $otp,
                "exp_to_verify" => $now->add(new DateInterval("PT2M")),
            ]);
            $mail_status = true;
            $email = Auth::user()->email;
            $data = $bankAcc->first();
            try {
                Mail::to($email)->send(new VerificationBank($data->bank_name, $data->acc_number, $otp));
            } catch (\Throwable $th) {
                ResendTrxNotification::writeErrorLog('App\Mail\VerificationBank', "bank verification", [$data->bank_name, $data->acc_number, $otp], $email);
                $mail_status = false;
            }
            return response()->json(["message" => "Verification failed. OTP expired. You will receive new OTP code to verify your bank account", "mail_status" => $mail_status], 403);
        }
        $bankAcc->update(["status" => 1]);
        return response()->json(["message" => "verification successfull"], 202);
    }

    public function banks(Request $req)
    {
        $bankAccs = $req->org->billAccs()->where('deleted', 0)->get();
        if (count($bankAccs) == 0) {
            return response()->json(["error" => "Bank Account data not found"], 404);
        }
        foreach ($bankAccs as $bankAcc) {
            $bankAcc->icon = '/storage/images/bank-icons/' . $bankAcc->bank_name . '.png';
            $bankAcc->bank_name = config('banks')[$bankAcc->bank_name];
        }
        return response()->json(["data" => $bankAccs], 200);
    }
    // maanagement withdraw
    public function createWd(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "event_id" => "required|string",
            "bank_acc_id" => "required|string"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        if (!$req->org->credibilityData()->first()) {
            return response()->json(["error" => "You are not allowed to create withdraw before create legality data first"], 403);
        }
        if ($req->org->credibilityData()->first()->status == 0) {
            return response()->json(["error" => "You are not allowed to create withdraw before ypur legality approved"], 403);
        }
        
        $event = Event::where('id', $req->event_id)->where('org_id', $req->org->id)->where('deleted', 0)->where(function($query){
            $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $query->where('is_publish', "<", 3)->where('end_date', "<=", $now->format('Y-m-d'))->where('end_time', '<', $now->format('H:i:s'))->orWhere('category', 'Attraction')->orWhere('category', 'Daily Activities')->orWhere('category', 'Tour Travel (recurring)');
        });
        $eventData = $event->first();
        if (!$eventData) {
            return response()->json(["error" => "Event data not found or cannot add to withdraw"], 404);
        }
        $bankAcc = BillAccount::where('id', $req->bank_acc_id)->where('org_id', $req->org->id)->where('status', 1)->first();
        if (!$bankAcc) {
            return response()->json(["error" => "Bank account data not found"], 404);
        }
        $basicAmount = 0;
        $tickets = Ticket::where('event_id', $eventData->id)->where('type_price', '!=', 1)->get();
        foreach ($tickets as $ticket) {
            foreach ($ticket->purchases()->get() as $purchase) {
                if ($purchase->payment()->first()->pay_state == 'SUCCEEDED') {
                    $basicAmount += intval($purchase->amount);
                }
            }
        }
        if ($eventData->category == 'Attraction' || $eventData->category == 'Daily Activities' || $eventData->category == 'Tour Travel (recurring)') {
            $hasWithdrawn = 0;
            foreach (Withdraw::where('event_id', $eventData->id)->get() as $wd) {
                $hasWithdrawn += intval($wd->basic_nominal);
            }
            $basicAmount -= $hasWithdrawn;
        } else {
            $event->update([
                "is_publish" => intval($eventData->is_publish) + 3
            ]);
        }
        $refundDatas = RefundData::where('event_id', $eventData->id)->where('approve_admin', true)->get();
        foreach ($refundDatas as $refundData) {
            $basicAmount += ($refundData->basic_nominal - $refundData->nominal);
        }
        $profitSetting = ProfitSetting::first();
        $nominal = ceil($basicAmount - ($basicAmount * $profitSetting->ticket_commision));
        $wd = Withdraw::create([
            'event_id' => $eventData->id,
            'org_id' => $req->org->id,
            'bill_acc_id' => $bankAcc->id,
            'nominal' => ($nominal <= 10000 ? $nominal : ($nominal - $profitSetting->admin_fee_wd)),
            'commision' => $basicAmount * $profitSetting->ticket_commision,
            'admin_fee_wd' => ($nominal <= 10000 ? 0 : $profitSetting->admin_fee_wd),
            'basic_nominal' => $basicAmount,
            'status' => 0
        ]);
        $user = Auth::user();
        try {
            Mail::to(config('agendakota.admin_email'))->send(new AdminWdNotification(
                $eventData->name,
                $eventData->id,
                $req->org->name,
                $req->org->id,
                ($nominal <= 10000 ? $nominal : ($nominal - $profitSetting->admin_fee_wd)),
                // (($basicAmount - ($basicAmount * (floatval(config('agendakota.commission'))))) - intval(config('agendakota.profit_plus'))),
                $bankAcc->acc_number,
                $user->name,
                $user->email,
                false,
                false
            ));
        } catch (\Throwable $th) {
            Withdraw::where('id', $wd->id)->delete();
            Log::info("Error With Mail Server : Failed send mail withdraw notification. Withdraw reset");
            return response()->json(["error" => "Mail server error. Please try again later"], 500);
        }
        return response()->json(["data" => $wd, "event" => $eventData, "bank" => $bankAcc], 201);
    }

    public function deleteWd(Request $req, $isAdmin = false)
    {
        $validator = Validator::make($req->all(), [
            "wd_id" => "required|string"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $wdObj = null;
        if ($isAdmin) {
            $wdObj = Withdraw::where('id', $req->wd_id);
        } else {
            $wdObj = Withdraw::where('id', $req->wd_id)->where('org_id', $req->org->id);
        }
        $wdData = $wdObj->first();
        if (!$wdData) {
            return response()->json(["error" => "Withdraw data not found"], 404);
        }
        if (($wdData->event()->first()->category == 'Attraction' || $wdData->event()->first()->category == 'Daily Activities' || $wdData->event()->first()->category == 'Tour Travel (recurring)') && $wdData->status == 1) {
            return response()->json(["error" => "Your aren't remove withdraw data of event with category Attraction, Daily Activities, or Tour Travel (recurring)"], 403);
        }
        if ($wdData->status == 0 && $wdData->event()->first()->category != 'Attraction' && $wdData->event()->first()->category != 'Daily Activities' && $wdData->event()->first()->category != 'Tour Travel (recurring)') {
            Event::where('id', $wdData->event()->first()->id)->update([
                "is_publish" => intval($wdData->event()->first()->is_publish) - 3
            ]);
        }
        $deleted = $wdObj->delete();
        return response()->json(["deleted" => $deleted], 202);
    }

    function deleteWdOrg(Request $req)
    {
        return $this->deleteWd($req, false);
    }

    public function getWd(Request $req, $isAdmin = false)
    {
        $validator = Validator::make($req->all(), [
            "wd_id" => "required|string"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $wd = null;
        if ($isAdmin) {
            $wd = Withdraw::where('id', $req->wd_id)->first();
        } else {
            $wd = Withdraw::where('id', $req->wd_id)->where('org_id', $req->org->id)->first();
        }
        if (!$wd) {
            return response()->json(["error" => "Withdraw data not found"], 404);
        }
        $wd->event = $wd->event()->first();
        $wd->organization = $wd->organization()->first();
        $wd->bank = $wd->billAcc()->first();
        $wd->legality_data = $wd->organization->credibilityData()->first();
        return response()->json(["withdraw" => $wd], 200);
    }

    public function getWdOrg(Request $req)
    {
        return $this->getWd($req, false);
    }

    public function wds(Request $req, $isAdmin = false)
    {
        $wds = null;
        if ($isAdmin) {
            $wds = Withdraw::all();
        } else {
            $wds = Withdraw::where('org_id', $req->org->id)->get();
        }
        if (count($wds) == 0) {
            return response()->json(["error" => "Withdraw data's not found"], 404);
        }
        foreach ($wds as $wd) {
            $wd->event = $wd->event()->first();
            $wd->organization = $wd->organization()->first();
            $wd->bank = $wd->billAcc()->first();
            $wd->legality_data = $wd->organization->credibilityData()->first();
        }
        return response()->json(["withdraws" => $wds], 200);
    }

    public function wdsOrg(Request $req)
    {
        return $this->wds($req, false);
    }

    public function availableForWdCore($event){
            $tickets = Ticket::where('event_id', $event->id)->where('type_price', '!=', 1)->get();
            $amount = 0;
            foreach ($tickets as $ticket) {
                foreach ($ticket->purchases()->get() as $purchase) {
                    if ($purchase->payment()->first()->pay_state == 'SUCCEEDED') {
                        $amount += intval($purchase->amount);
                    }
                }
            }
            if ($event->category == 'Attraction' || $event->category == 'Daily Activities' || $event->category == 'Tour Travel (recurring)') {
                $hasWithdrawn = 0;
                foreach (Withdraw::where('event_id', $event->id)->where('status', '!=', -1)->get() as $wd) {
                    $hasWithdrawn += intval($wd->basic_nominal);
                }
                $amount -= $hasWithdrawn;
            }
            $refundDatas = RefundData::where('event_id', $event->id)->where('approve_admin', true)->get();
            foreach ($refundDatas as $refundData) {
                $amount += ($refundData->basic_nominal - $refundData->nominal);
            }
            $profitSetting = ProfitSetting::first();
            $orginAmount = $amount;
            $amount = ceil($amount - (intval($amount) * $profitSetting->ticket_commision));
            if ($amount > 10000) {
                $amount -= $profitSetting->admin_fee_wd;
            }
            $totalAmount = $amount;
            $events = [
                "event" => $event,
                "amount" => $amount,
                "origin_amount" => $orginAmount,
                "commision" => intval($orginAmount) * $profitSetting->ticket_commision,
                "admin_fee" => $amount <= 10000 ? 0 : $profitSetting->admin_fee_wd
            ];
            return ["total" => $totalAmount, "events" => $events];
    }

    public function availableForWd(Request $req)
    {
        $events = [];
        $totalAmount = 0;
        foreach (Event::where('org_id', $req->org->id)->where('deleted', 0)->where(function($query){
            $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $query->where('is_publish', '<', 3)->where('end_date', '<=', $now->format('Y-m-d'))->where('end_time', '<', $now->format('H:i:s'))->orWhere('category', 'Attraction')->orWhere('category', 'Daily Activities')->orWhere('category', 'Tour Travel (recurring)');
        })->get() as $event) {
            $data = $this->availableForWdCore($event);
            $events[] = $data["events"];
            $totalAmount += $data["total"];
        }
        return response()->json(["data" => $events, "total_amount" => $totalAmount], 200);
    }

    private function createDisburstment($disburstment)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.xendit.co/disbursements',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($disburstment),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-IDEMPOTENCY-KEY: ' . time(),
                'Authorization: Basic ' . base64_encode(env('XENDIT_API_WRITE') . ':')
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response);
    }

    public function changeStateWd(Request $req)
    {
        // For Admin privillege
        $validator = Validator::make($req->all(), [
            "wd_id" => "required|string",
            "state" => "required|numeric"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        // Status number :
        // 1 => Accept
        // 0 => Pending
        // -1 => Reject
        // NB : State withdraw can't rollback
        $manual = $req->set_finish ? ($req->set_finish == 1 ? true : false) : false;
        if ($req->state != 1 && $req->state != 0 && $req->state != -1) {
            return response()->json(["error" => "State code is not recognized"], 403);
        }
        $wdObj = Withdraw::where('id', $req->wd_id)->where('status', 0);
        if (!$wdObj->first()) {
            return response()->json(["error" => "Data withdraw not found", 404]);
        }
        if ($req->state == 1) {
            $wd = $wdObj->first();
            $bank = $wd->billAcc()->first();
            $org = $wd->organization()->first();
            $legality = $org->credibilityData()->first();
            if (!$legality) {
                return response()->json(["error" => "This organization haven't legality data. Please prompt this organization to completed it first"], 403);
            } else if ($legality->status == false) {
                return response()->json(["error" => "Please check and approve / accept legality data of this organization"], 403);
            }
            if(!$manual){
                $uniqueExternal  = uniqid('external_wd_', true);
                $localDisburstment = DisburstmentWd::create([
                    'disburstment_id' => $uniqueExternal,
                    'withdraw_id' => $wd->id
                ]);
                $res = $this->createDisburstment([
                    "external_id" => $uniqueExternal,
                    "amount" => $wd->nominal,
                    "bank_code" =>  $bank->bank_name,
                    "account_holder_name" =>  $bank->acc_name,
                    "account_number" => $bank->acc_number,
                    "description" => "Withdraw payment from event (" . $wd->event()->first()->name . " - " . $wd->event()->first()->id . ") and organizer (" . $org->name . " - " . $org->id . ")",
                ]);
                if (isset($res->error_code)) {
                    Disbursements::where('id', $localDisburstment->id)->delete();
                    return response()->json([
                        "error" => "Failed process in xendit API",
                        "message" => $res->message
                    ], 403);
                }
            }
        }
        $eventObj = $wdObj->first()->event();
        if (
            $req->state == 1 &&
            ($eventObj->first()->category != 'Attraction' && $eventObj->first()->category != 'Daily Activities' && $eventObj->first()->category != 'Tour Travel (recurring)')
        ) {
            $eventObj->update([
                "is_publish" => 3
            ]);
        } else if (
            $req->state == -1 &&
            ($eventObj->first()->category != 'Attraction' && $eventObj->first()->category != 'Daily Activities' && $eventObj->first()->category != 'Tour Travel (recurring)')
        ) {
            Event::where('id', $eventObj->first()->id)->update([
                "is_publish" => intval($eventObj->first()->is_publish) - 3
            ]);
        }
        $updated = $wdObj->update($manual && $req->state === 1 ? ["status" => $req->state, 'finish' => true, "mode" => "manual"] : ["status" => $req->state]);
        return response()->json(["updated" => $updated], 202);
    }
}
