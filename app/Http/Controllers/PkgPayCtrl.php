<?php

// <!-- NOTE : POSTPOND FIRST THIS CTRL -->

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Event;
use App\Models\Organization;
use App\Models\PkgPayment;
use App\Models\PkgPricing;
use Xendit\Xendit;
use DateTime;
use DateTimeZone;
use DateInterval;

class PkgPayCtrl extends Controller
{
    public function listPayMethod()
    {
        return response()->json(["method_payments" => config('payconfigs.methods')], 200);
    }

    private function createEWallet($eventId, $pkg, $code_method, $mobileNumber = null, $cashtag = null)
    {
        $methods = config('payconfigs.methods');
        if (!$methods["e-wallet"][$code_method]) {
            return response()->json(["error" => "Payment method not found"], 404);
        }
        Xendit::setApiKey(env('XENDIT_API_WRITE'));
        $orderId = uniqid("pkg_trx_ewallet", true);
        $params = [
            'reference_id' => $orderId,
            'currency' => 'IDR',
            'amount' => $pkg->price,
            'checkout_method' => 'ONE_TIME_PAYMENT',
            'channel_code' => $methods["e-wallet"][$code_method][0],
        ];
        if ($code_method == "014") {
            if ($mobileNumber == null) {
                return ["error" => "Mobile number is required if you choose the OVO method", "status" => 402];
            }
            $params += [
                'channel_properties' => [
                    'mobile_number' => '+62' . substr($mobileNumber, 2),
                ],
            ];
        } else if ($code_method == "015") {
            if ($cashtag == null) {
                return ["error" => '$' . "Cashtag is required if you choose the Jenius Pay method", "status" => 402];
            }
            $cashtag = preg_replace('/[^a-zA-Z0-9 ]/m', '', $cashtag);
            $params += [
                'channel_properties' => [
                    'cashtag' => '$' . $cashtag,
                ],
            ];
        } else {
            $params += [
                'channel_properties' => [
                    // NOTE : Replace this route with page event mng ReactJS
                    'success_redirect_url' => route('pkg.payment.redirect'),
                ],
            ];
        }
        // dd($params);
        $createEWalletCharge = \Xendit\EWallets::createEWalletCharge($params);
        $savePay = PkgPayment::create([
            'event_id' => $eventId,
            'pkg_id' => $pkg->id,
            'token_trx' => $createEWalletCharge["id"],
            'pay_state' => $createEWalletCharge["status"],
            'order_id' => $orderId,
            'price' => $pkg->price
        ]);
        return ["data" => $savePay, "payment" => $createEWalletCharge, "status" => 201];
    }

    private function createQRis($eventId, $pkg, $code_method)
    {
        if (!config('payconfigs.methods')["qris"][$code_method]) {
            return response()->json(["error" => "Payment method not found"], 404);
        }
        $now24 = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $orderId = uniqid("pkg_trx_qris", true);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.xendit.co/qr_codes',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => "CURL_HTTP_VERSION_1_1",
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                "reference_id" => $orderId,
                "type" => "DYNAMIC",
                "currency" => "IDR",
                "amount" => $pkg->price,
                "channel_code" => config('payconfigs.methods')["qris"][$code_method][0],
                "expires_at" => str_replace(' ', 'T', $now24->add(new DateInterval('PT24H'))->format('Y-m-d H:i:s')) . 'Z',
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'api-version: 2022-07-31',
                'Authorization: ' . 'Basic ' . base64_encode(env('XENDIT_API_WRITE') . ':')
            ),
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response);
        $savePay = PkgPayment::create([
            'event_id' => $eventId,
            'pkg_id' => $pkg->id,
            'token_trx' => $response->id,
            'pay_state' => "PENDING",
            'order_id' => $orderId,
            'price' => $pkg->price
        ]);
        return ["data" => $savePay, "payment" => $response, "status" => 201];
    }

    private function createVirAccount($eventId, $pkg, $code_method)
    {
        $methods = config('payconfigs.methods');
        if (!$methods["VA"][$code_method]) {
            return response()->json(["error" => "Payment method not found"], 404);
        }
        $event = Event::where('id', $eventId)->first();
        $now24 = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $orderId = uniqid("pkg_trx_va", true);
        Xendit::setApiKey(env('XENDIT_API_WRITE'));
        $createVA = \Xendit\VirtualAccounts::create([
            "external_id" => $orderId,
            "bank_code" => $methods["VA"][$code_method][0],
            "name" => $event->name,
            "is_single_use" => true,
            "is_closed" => true,
            "expected_amount" => $pkg->price,
            "expiration_date" => str_replace(' ', 'T', $now24->add(new DateInterval('PT24H'))->format('Y-m-d H:i:s')) . 'Z',
        ]);
        $savePay = PkgPayment::create([
            'event_id' => $eventId,
            'pkg_id' => $pkg->id,
            'token_trx' => $createVA["id"],
            'pay_state' => "PENDING",
            'order_id' => $orderId,
            'price' => $pkg->price
        ]);
        return ["data" => $savePay, "payment" => $createVA, "status" => 201];
    }

    public function createTrx($eventId, $pkg_id, $code_method, $mobileNumber = null, $cashtag = null)
    {
        $pkg = PkgPricing::where('id', $pkg_id)->first();
        if ($pkg->price == 0) {
            $orderId = uniqid("pkg_trx_free", true);
            $savePay = PkgPayment::create([
                'event_id' => $eventId,
                'pkg_id' => $pkg->id,
                'token_trx' => '-',
                'pay_state' => "SUCCEEDED",
                'order_id' => $orderId,
                'price' => $pkg->price
            ]);
            return ["data" => $savePay, "status" => 201];
        }
        if (intval($code_method) >= 11 && intval($code_method) <= 15) {
            return $this->createEWallet($eventId, $pkg, $code_method, $mobileNumber, $cashtag);
        } else if (intval($code_method) >= 21 && intval($code_method) <= 22) {
            return $this->createQRis($eventId, $pkg, $code_method);
        } else if (intval($code_method) >= 31 && intval($code_method) <= 41) {
            return $this->createVirAccount($eventId, $pkg, $code_method);
        } else {
            return $this->createVirAccount($eventId, $pkg, '035');
        }
    }

    public function createTrxEd(Request $req, $eventId)
    {
        $validator = Validator::make($req->all(), [
            "pay_method" => "required|string",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $code_method = $req->pay_method;
        $pkg = PkgPricing::where('id', $req->pkg_id)->first();
        if (intval($code_method) >= 11 && intval($code_method) <= 15) {
            return response()->json($this->createEWallet($eventId, $pkg, $code_method, $req->mobile_number, $req->cashtag), 200);
        } else if (intval($code_method) >= 21 && intval($code_method) <= 22) {
            return response()->json($this->createQRis($eventId, $pkg, $code_method), 200);
        } else if (intval($code_method) >= 31 && intval($code_method) <= 41) {
            return response()->json($this->createVirAccount($eventId, $pkg, $code_method), 200);
        } else {
            return response()->json($this->createVirAccount($eventId, $pkg, '035'), 200);
        }
    }

    private function getTrxEWallet($event)
    {
        Xendit::setApiKey(env('XENDIT_API_READ'));
        $eWalletStatus = \Xendit\EWallets::getEWalletChargeStatus($event->payment()->first()->token_trx);
        return response()->json(["payment" => $eWalletStatus], 200);
    }

    private function getTrxQris($event)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.xendit.co/qr_codes/' . $event->payment()->first()->token_trx,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => "CURL_HTTP_VERSION_1_1",
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'api-version: 2022-07-31',
                'Authorization: ' . 'Basic ' . base64_encode(env('XENDIT_API_READ') . ':')
            ),
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response);
        return response()->json(["payment" => $response], 200);
    }

    private function getTrxVirAccount($event)
    {
        Xendit::setApiKey(env('XENDIT_API_READ'));
        $vaStatus = \Xendit\VirtualAccounts::retrieve($event->payment()->first()->token_trx);
        return response()->json(["payment" => $vaStatus]);
    }

    public function getTrx(Request $req, $orgId)
    {
        $event = Event::where('id', $req->event_id)->where('org_id', $orgId)->where('deleted', 0)->first();
        if (!$event) {
            return response()->json(["error" => "Your event data not found"], 404);
        }
        if (preg_match('/pkg_trx_va/i', $event->payment()->first()->order_id)) {
            return $this->getTrxVirAccount($event);
        } else if (preg_match('/pkg_trx_ewallet/i', $event->payment()->first()->order_id)) {
            return $this->getTrxEWallet($event);
        } else if (preg_match('/pkg_trx_qris/i', $event->payment()->first()->order_id)) {
            return $this->getTrxQris($event);
        } else {
            return response()->json([
                "payment" => $event->payment()->first()
            ], 200);
        }
    }

    public function renewTransaction(Request $req, $orgId)
    {
        $event = Event::where('id', $req->event_id)->where('org_id', $orgId)->where('deleted', 0)->first();
        if (!$event) {
            return response()->json(["error" => "Your event data not found"], 404);
        }
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        if (
            $event->payment()->first()->pay_state == "SUCCEEDED" ||
            $now > new DateTime($event->start_date . ' ' . $event->start_time, new DateTimeZone('Asia/Jakarta'))
        ) {
            return response()->json(["error" => "Your event payment cannot renewing, because this event has expired or your payment have finished"], 403);
        }
        $req->pkg_id = $event->payment()->first()->pkg_id;
        PkgPayment::where('id', $event->payment()->first()->id)->delete();
        return $this->createTrxEd($req, $event->id);
    }
}
