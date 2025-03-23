<?php
namespace App\Http\Controllers;

use App;
use App\Mail\AdminRefundNotification;
use App\Mail\ETicket;
use App\Mail\TrxNotification;
use App\Mail\UserRefundNotification;
use App\Models\DailyTicket;
use App\Models\DisburstmentRefund;
use App\Models\Payment;
use App\Models\ProfitSetting;
use App\Models\Purchase;
use App\Models\RefundData;
use App\Models\RefundSetting;
use App\Models\ReservedSeat;
use App\Models\Ticket;
use App\Models\Voucher;
use Barryvdh\Snappy\Facades\SnappyPdf;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PchCtrl extends Controller
{
    private function setTrxEWallet($payId, $code_method, $amount, $taxTotal, $profitSetting, $mobileNumber = null, $cashtag = null)
    {
        $methods = config('payconfigs.methods');
        if (! $methods["e-wallet"][$code_method]) {
            return response()->json(["error" => "Payment method not found"], 404);
        }
        $orderId     = uniqid("trx_ewallet", true);
        $platformFee = $profitSetting->mul_pay_gate_fee * ($methods["e-wallet"][$code_method][2] * ($amount - $taxTotal));
        $amount      = ceil($amount + $profitSetting->admin_fee_trx + $platformFee);
        $params      = [
            'reference_id'    => $orderId,
            'currency'        => 'IDR',
            'amount'          => $amount,
            'checkout_method' => 'ONE_TIME_PAYMENT',
            'channel_code'    => $methods["e-wallet"][$code_method][0],
        ];

        if ($code_method == "014") {
            if ($mobileNumber == null) {
                return ["error" => "Mobile number is required if you choose the OVO method", "status" => 402];
            }
            $params += [
                'channel_properties' => [
                    'mobile_number' => str_split($mobileNumber)[0] == '0' ? ('+62' . substr($mobileNumber, 1)) : ('+' . $mobileNumber),
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
                    'success_redirect_url' => env("FRONTEND_URL") . "/my-tickets",
                ],
            ];
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.xendit.co/ewallets/charges',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: ' . 'Basic ' . base64_encode(env('XENDIT_API_WRITE') . ':'),
            ],
        ]);

        $createEWalletCharge = curl_exec($curl);
        curl_close($curl);
        $createEWalletCharge = json_decode($createEWalletCharge);
        $now                 = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        Payment::where('id', $payId)->update(
            [
                'token_trx'    => $createEWalletCharge->id,
                'pay_state'    => $createEWalletCharge->status,
                'order_id'     => $orderId,
                'price'        => $amount,
                'admin_fee'    => $profitSetting->admin_fee_trx,
                'platform_fee' => $platformFee,
                'code_method'  => $code_method,
                'pay_links'    => $createEWalletCharge->actions ? ($createEWalletCharge->actions->desktop_web_checkout_url ? $createEWalletCharge->actions->desktop_web_checkout_url : ($createEWalletCharge->actions->mobile_web_checkout_url ? $createEWalletCharge->actions->mobile_web_checkout_url : $createEWalletCharge->actions->mobile_deeplink_checkout_url)) : '',
                'expired'      => $now->add(new DateInterval($code_method == "014" ? 'PT1M' : 'PT30M'))->format('Y-m-d H:i:s'),
            ]
        );
        return [
            "payment"  => $createEWalletCharge,
            "platform" => $platformFee,
            "total"    => $amount,
            "status"   => 201,
        ];
    }

    private function setTrxQris($payId, $code_method, $amount, $taxTotal, $profitSetting)
    {
        $methods = config('payconfigs.methods')["qris"][$code_method];
        if (! $methods) {
            return response()->json(["error" => "Payment method not found"], 404);
        }
        $now24       = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $orderId     = uniqid("trx_qris", true);
        $curl        = curl_init();
        $platformFee = $profitSetting->mul_pay_gate_fee * ($methods[2] * ($amount - $taxTotal));
        $amount      = ceil($amount + $profitSetting->admin_fee_trx + $platformFee);
        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL            => 'https://api.xendit.co/qr_codes',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => "CURL_HTTP_VERSION_1_1",
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode(
                    [
                        "reference_id" => $orderId,
                        "type"         => "DYNAMIC",
                        "currency"     => "IDR",
                        "amount"       => $amount,
                        "channel_code" => config('payconfigs.methods')["qris"][$code_method][0],
                        "expires_at"   => str_replace(' ', 'T', $now24->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s')) . 'Z',
                        // "expires_at" => str_replace(' ', 'T', $now24->add(new DateInterval('PT7H'))->format('Y-m-d H:i:s')) . 'Z',
                    ]
                ),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'api-version: 2022-07-31',
                    'Authorization: ' . 'Basic ' . base64_encode(env('XENDIT_API_WRITE') . ':'),
                ],
            ]
        );
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response);
        Payment::where('id', $payId)->update(
            [
                'token_trx'    => $response->id,
                'pay_state'    => "PENDING",
                'order_id'     => $orderId,
                'price'        => $amount,
                'admin_fee'    => $profitSetting->admin_fee_trx,
                'platform_fee' => $platformFee,
                'code_method'  => $code_method,
                'expired'      => $now24->format('Y-m-d H:i:s'),
                'qr_str'       => $response->qr_string,
            ]
        );
        return [
            "payment"  => $response,
            "platform" => $platformFee,
            "total"    => $amount,
            "status"   => 201];
    }

    private function setTrxVirAccount($payId, $code_method, $amount, $profitSetting)
    {
        $methods = config('payconfigs.methods');
        if (! $methods["VA"][$code_method]) {
            return response()->json(["error" => "Payment method not found"], 404);
        }
        $payment = Payment::where('id', $payId);
        $now24   = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $orderId = uniqid("trx_va", true);

        $platformFee = $profitSetting->mul_pay_gate_fee * $methods["VA"][$code_method][2];
        $amount      = ceil($amount + $profitSetting->admin_fee_trx + $platformFee);
        $params      = [
            "external_id"     => $orderId,
            "bank_code"       => $methods["VA"][$code_method][0],
            "name"            => str_replace('-', ' ', $payment->first()
                    ->purchases()->get()[0]
                    ->ticket()->first()
                    ->event()->first()
                    ->slug),
                "is_single_use"   => true,
                "is_closed"       => true,
                "expected_amount" => $amount,
                "expiration_date" => str_replace(' ', 'T', $now24->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s')) . 'Z',
            // "expiration_date" => str_replace(' ', 'T', $now24->add(new DateInterval('PT7H'))->format('Y-m-d H:i:s')) . 'Z',
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.xendit.co/callback_virtual_accounts',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: ' . 'Basic ' . base64_encode(env('XENDIT_API_WRITE') . ':'),
            ],
        ]);

        $createVA = curl_exec($curl);
        curl_close($curl);
        Log::info($createVA);
        $createVA = json_decode($createVA);

        $payment->update(
            [
                'token_trx'    => $createVA->id,
                'pay_state'    => "PENDING",
                'order_id'     => $orderId,
                'price'        => $amount,
                'admin_fee'    => $profitSetting->admin_fee_trx,
                'platform_fee' => $platformFee,
                'code_method'  => $code_method,
                'expired'      => $now24->format('Y-m-d H:i:s'),
                'virtual_acc'  => $createVA->account_number,
            ]
        );
        return [
            "payment"  => $createVA,
            "platform" => $platformFee,
            "total"    => $amount,
            "status"   => 201];
    }

    public function loadTrxData()
    {
        $now      = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $payments = Payment::where('pay_state', 'PENDING')->where('expired', '<', $now->format('Y-m-d H:i:s'))->get();
        // Log::info($payments);
        // $test = [];
        foreach ($payments as $payment) {
            $datePayCreate = new DateTime($payment->expired, new DateTimeZone('Asia/Jakarta'));
            $datePayCreate = $datePayCreate->add(new DateInterval('PT5M'));
            // array_push($test, [$datePayCreate, $now]);
            if ($datePayCreate < $now) {
                $purchases = $payment->purchases()->get()->groupBy('ticket_id');
                foreach ($purchases as $key => $value) {
                    Ticket::where('id', $key)->where('type_price', '!=', 1)->where('quantity', '!=', -1)->update(
                        [
                            'quantity' => intval($value[0]->ticket()->first()->quantity) + count($value),
                        ]
                    );
                    foreach ($value as $pch) {
                        if ($pch->amount != 0) {
                            DailyTicket::where('purchase_id', $pch->id)->delete();
                            ReservedSeat::where('pch_id', $pch->id)->delete();
                            Purchase::where('id', $pch->id)->update(["code" => '-']);
                        }
                    }
                }
                Payment::where('id', $payment->id)->update(
                    [
                        'pay_state' => "EXPIRED",
                    ]
                );
            }
        }
        // return $test;
    }

    private function rollbackPurchase($ticket_ids, $ticket_datas, $payment, $forOrgInv)
    {
        $i          = 0;
        $allWhenSql = "case ";
        $allIds     = [];
        if ($forOrgInv == false) {
            foreach ($ticket_ids as $key => $value) {
                array_push($allIds, $key);
                $allWhenSql .= ("when id='" . $key . "' then '" . ($ticket_datas[$i]->quantity + $value) . "' ");
                $i++;
            }
            if ($allWhenSql != "case ") {
                $allWhenSql .= " end";
                Ticket::whereIn('id', $allIds)->where('quantity', '!=', -1)->update(
                    [
                        "quantity" => DB::raw($allWhenSql),
                    ]
                );
            }
        }
        Payment::where('id', $payment->id)->delete();
    }

    private function basicValidator($req, $ticket_ids, $now, bool $forOrgInv = false)
    {
        $customPriceTickets    = [];
        $dailyTickets          = [];
        $seatNumberTickets     = [];
        $dailyTicketsData      = [];
        $seatNumberTicketsData = [];
        $ticketIds             = array_keys($ticket_ids);
        $tickets               = Ticket::whereIn('id', $ticketIds)->
            where('deleted', 0)->orderByRaw("FIELD(id, '" . implode("','", $ticketIds) . "')")->
            with(['event', 'purchases', 'limitDaily', 'purchases.payment', 'event.availableDays'])->
            lockForUpdate()->
            get();
        if (count($tickets) != count($ticket_ids)) {
            return ["error" => "Ticket is not found", "code" => 404];
        }
        $index = 0;
        $user  = Auth::user();
        foreach ($ticket_ids as $key => $value) {

            if ($tickets[$index]->type_price == 3 && ! $req->custom_prices && ! $forOrgInv) {
                return ["error" => "Custom prices field is required for custom price ticket", "code" => 403];
            }
            if ($tickets[$index]->type_price != 1 && ! $req->pay_method && ! $forOrgInv) {
                return ["error" => "pay_method code is required", "code" => 403];
            }
            if (intval($tickets[$index]->max_purchase) < $value && ! $forOrgInv && ($tickets[$index]->event->category != 'Attraction' && $tickets[$index]->event->category != 'Daily Activities' && $tickets[$index]->event->category != 'Tour Travel (recurring)')) {
                return ["error" => "Max purchases is " . $tickets[$index]->max_purchase . " / user", "code" => 403];
            }
            if (($tickets[$index]->event->is_publish == 1 || $tickets[$index]->event->is_publish >= 3) && ! $forOrgInv) {
                return ["error" => "This has not been published yet or is still in draft form", "code" => 403];
            }

            $startTicket = new DateTime($tickets[$index]->start_date . ' 00:00:00', new DateTimeZone('Asia/Jakarta'));
            $endTicket   = new DateTime($tickets[$index]->end_date === $tickets[$index]->event->end_date ? ($tickets[$index]->end_date . ' ' . $tickets[$index]->event->end_time) : ($tickets[$index]->end_date . ' 23:59:00'), new DateTimeZone('Asia/Jakarta'));

            // $purchases = $tickets[$index]->purchases()->where('user_id', $user->id)->get();

            $hasPayments = false;
            if (! $forOrgInv) {
                $purchases = [];
                foreach ($tickets[$index]->purchases as $pch) {
                    if ($pch->user_id == $user->id) {
                        array_push($purchases, $pch);
                    }
                }
                foreach ($purchases as $pch) {
                    if ($tickets[$index]->event->single_trx == 1 && $pch->payment->pay_state != 'EXPIRED' && $pch->payment->user_id == $user->id) {
                        $hasPayments = true;
                        break;
                    } else if ($pch->payment->pay_state === 'PENDING' && $pch->payment->user_id == $user->id) {
                        $hasPayments = true;
                        break;
                    }
                }
            }
            if ($hasPayments && ! $forOrgInv) {
                return ["error" => $tickets[$index]->event->single_trx == 1 ? "The event of this ticket accepted single transaction only" : "Please complete your last transaction first before create new transaction", "code" => 403];
            }

            if ($now > new DateTime($tickets[$index]->event->end_date . ' ' . $tickets[$index]->event->end_time, new DateTimeZone('Asia/Jakarta'))) {
                return ["error" => "This event or event ticket has been expired", "code" => 403];
            }
            if (($tickets[$index]->event->category != 'Attraction' && $tickets[$index]->event->category != 'Daily Activities' && $tickets[$index]->event->category != 'Tour Travel (recurring)')
                && ($startTicket > $now || $endTicket < $now)
            ) {
                return ['error' => "Ticket " . $tickets[$index]->name . " is not yet available", "code" => 403];
            }
            if (($tickets[$index]->event->category != 'Attraction' && $tickets[$index]->event->category != 'Daily Activities' && $tickets[$index]->event->category != 'Tour Travel (recurring)')
                && intval($tickets[$index]->quantity) < $value && ! $forOrgInv
            ) {
                return ["error" => "Only " . $tickets[$index]->quantity . " tickets left for your selected id", "code" => 403];
            }
            if (($tickets[$index]->event->category == 'Attraction' || $tickets[$index]->event->category == 'Daily Activities' || $tickets[$index]->event->category == 'Tour Travel (recurring)')
                && ! $req->visit_dates
            ) {
                return ["error" => "Visit dates form is required for event with type attraction, daily activities, or tour travel (recurring)", "code" => 403];
            }
            if (count($tickets[$index]->event->availableDays) == 0 && ($tickets[$index]->event->category == 'Attraction' || $tickets[$index]->event->category == 'Daily Activities' || $tickets[$index]->event->category == 'Tour Travel (recurring)')) {
                return ["error" => "Tickets not yet available for this event in this date or day", "code" => 404];
            }
            if (($tickets[$index]->seat_number == true || $tickets[$index]->seat_number == 1) && ! $req->seat_numbers) {
                return ["error" => "Set numbers options is required for this ticket", "code" => 403];
            }
            if ($tickets[$index]->type_price == 3 && ! $forOrgInv) {
                $customPriceTickets[$key] = $value;
            }
            if ($tickets[$index]->event->category == 'Attraction' || $tickets[$index]->event->category == 'Daily Activities' || $tickets[$index]->event->category == 'Tour Travel (recurring)') {
                $dailyTickets[$key]     = $value;
                $dailyTicketsData[$key] = $tickets[$index];
            }
            if ($tickets[$index]->seat_number == true || $tickets[$index]->seat_number == 1) {
                $seatNumberTickets[$key]     = $value;
                $seatNumberTicketsData[$key] = $tickets[$index];
            }
            $index++;
        }
        return [
            "customPriceTickets"    => $customPriceTickets,
            "dailyTickets"          => $dailyTickets,
            "seatNumberTickets"     => $seatNumberTickets,
            "dailyTicketsData"      => $dailyTicketsData,
            "seatNumberTicketsData" => $seatNumberTicketsData,
            "all_ticket"            => $tickets,
        ];
    }

    private function voucherValidator($req, $voucher, $now)
    {
        $remainingVoucher = 0;
        if ($req->voucher_code) {
            $start       = new DateTime($voucher->start, new DateTimeZone('Asia/Jakarta'));
            $end         = new DateTime(explode(' ', $voucher->end)[0] . " 23:59:59", new DateTimeZone('Asia/Jakarta'));
            $purchasesVc = [];
            foreach (Purchase::where('code', $req->voucher_code)->with(['payment'])->get() as $pch) {
                if ($pch->payment->pay_state !== "EXPIRED") {
                    array_push($purchasesVc, $pch);
                }
            }
            if (intval($voucher->quantity) <= count($purchasesVc) || $now < $start || $now > $end) {
                return ["error" => "This voucher is no longer available", "code" => 404];
            }
            $remainingVoucher = intval($voucher->quantity) - count($purchasesVc);
        }
        return ["remainingVoucher" => $remainingVoucher];
    }

    private function customPriceValidator($req, $customPriceTickets)
    {
        $customPrices = [];
        if ($req->custom_prices && count($customPriceTickets) > 0) {
            $customPrices = (array) $req->custom_prices;
            if (count($customPrices) == 0 || count($customPrices) != count($customPriceTickets)) {
                return ["error" => "Custom prices field is required for custom price ticket or count custom prices key not match with list of ticket id", "code" => 403];
            }
            foreach ($customPrices as $key => $prices) {
                if (! array_key_exists($key, $customPriceTickets)) {
                    return ["error" => "Custom price ticket id key not match with list of ticket_ids", "code" => 403];
                }
                if (count($prices) !== $customPriceTickets[$key]) {
                    return ["error" => "Custom prices list count,not equal with count of quantity tickets", "code" => 403];
                }
                foreach ($prices as $value) {
                    if (intval($value) < 10000) {
                        return ["error" => "Sorry, minimum transaction of one paid (custom_price field) ticket is IDR Rp. 10.000,-", "code" => 403];
                    }
                }
            }
        }
        return ["customPrices" => $customPrices];
    }

    private function visitDatesValidator($req, $dailyTickets, $dailyTicketsData, $now, bool $forOrgInv = false)
    {
        $visitDates = [];
        if ($req->visit_dates && count($dailyTickets) > 0) {
            $visitDates = (array) $req->visit_dates;
            if (count($visitDates) == 0 || count($visitDates) != count($dailyTickets)) {
                return ["error" => count($visitDates) == 0 ? "Visit Date is can't blank if you choose a ticket with daily type or count visit dates key not match with list of ticket id" : "Plase input visit dates data according to the ticket and event type", "code" => 403];
            }
            $pchsTcDates = DB::table('purchases')
                ->select(DB::raw('purchases.ticket_id as ticketId, daily_tickets.visit_date as visitDate, count(purchases.id) as qty'))
                ->join('daily_tickets', 'purchases.id', '=', 'daily_tickets.purchase_id')
                ->whereIn('purchases.ticket_id', array_keys($visitDates))
                ->whereIn('daily_tickets.visit_date', array_unique(array_reduce($visitDates, function ($last, $curr) {
                    if (! $last) {
                        return $curr;
                    }
                    return array_merge($last, $curr);
                })))
            // ->orderByRaw("FIELD(purchases.ticket_id, '".implode("','", array_keys($visitDates))."')")
                ->groupBy('purchases.ticket_id', 'daily_tickets.visit_date')
                ->get()->toArray();

            foreach ($visitDates as $key => $value) {
                if (! array_key_exists($key, $dailyTickets) || (count($value) == 0 || ! is_array($value))) {
                    return ["error" => "Visit Date is can't blank if you choose a ticket with daily type", "code" => 403];
                }
                if (count($value) !== $dailyTickets[$key]) {
                    return ["error" => "Count of vist dates not equal with quanity of ticket", "code" => 403];
                }

                foreach (array_count_values($value) as $date => $count) {
                    try {
                        $dateFormat = new DateTime($date, new DateTimeZone('Asia/Jakarta'));
                    } catch (\Throwable $th) {
                        return ["error" => "Invalid date format", "code" => 403];
                    }
                    if ($now->format('Y-m-d') > $dateFormat->format('Y-m-d')) {
                        return ["error" => "Visit date must be greater than date now", "code" => 403];
                    }
                    if (intval($dailyTicketsData[$key]->max_purchase) < $count && ! $forOrgInv) {
                        return ["error" => "Max purchases is " . $dailyTicketsData[$key]->max_purchase . " / user", "code" => 403];
                    }
                    $availableDay = null;
                    for ($i = 0; $i < count($dailyTicketsData[$key]->event->availableDays); $i++) {
                        if ($dailyTicketsData[$key]->event->availableDays[$i]->day == $dateFormat->format('D')) {
                            $availableDay = $dailyTicketsData[$key]->event->availableDays[$i];
                            $i            = count($dailyTicketsData[$key]->event->availableDays);
                        }
                    }
                    if (! $availableDay) {
                        return ["error" => "This ticket not yet available for this event in this date or day", "code" => 404];
                    }
                    $hasPurchased = array_filter($pchsTcDates, function ($val) use ($key, $dateFormat) {
                        $date = new DateTime($val->visitDate, new DateTimeZone('Asia/Jakarta'));
                        return $val->ticketId == $key && $date->format('Y-m-d') == $dateFormat->format('Y-m-d');
                    });
                    if ((intval($dailyTicketsData[$key]->limitDaily->limit_quantity) - (count($hasPurchased) == 0 ? 0 : reset($hasPurchased)->qty)) < $count && ! $forOrgInv) {
                        return ["error" => "Limit ticket for " . $dateFormat->format('Y-m-d') . " has been reached", "code" => 403];
                    }
                    $limitTime = new DateTime($availableDay->max_limit_time, new DateTimeZone('Asia/Jakarta'));
                    if ($now->format('Y-m-d') == $dateFormat->format('Y-m-d') && $now->format("H:i") >= $limitTime->format("H:i")) {
                        return ["error" => "Sorry, this ticket is closed for this time. Please reserve again before " . $limitTime->format("H:i"), "code" => 403];
                    }
                }
            }
        }
        return ["visitDates" => $visitDates];
    }

    private function seatNumbersValidator($req, $seatNumberTickets, $seatNumberTicketsData, $dailyTickets, $visitDates)
    {
        $seatNumbers = [];
        if ($req->seat_numbers && count($seatNumberTickets) > 0) {
            $seatNumbers = (array) $req->seat_numbers;
            if (count($seatNumbers) == 0 || count($seatNumbers) != count($seatNumberTickets)) {
                return ["error" => count($seatNumbers) == 0 ? "Seat number is can't blank if you choose a ticket with seat nummber option" : "Please input seat number data according to the ticket type", "code" => 403];
            }
            $hasPurchasedRaw = null;
            if (count($visitDates) == 0) {
                $hasPurchasedRaw = DB::table('purchases')->
                    select(DB::raw("purchases.ticket_id AS ticketId, reserved_seats.seat_number AS seatNumber"))->
                    join('reserved_seats', 'reserved_seats.pch_id', '=', 'purchases.id')->
                    whereIn('purchases.ticket_id', array_keys($seatNumbers))->
                    whereIn('reserved_seats.seat_number', array_unique(array_reduce($seatNumbers, function ($last, $curr) {
                    if (! $last) {
                        return $curr;
                    }
                    return array_merge($last, $curr);
                })))->
                    get()->toArray();
            } else {
                $hasPurchasedRaw = DB::table('purchases')->
                    select(DB::raw("purchases.ticket_id AS ticketId, daily_tickets.visit_date AS visitDate, reserved_seats.seat_number AS seatNumber"))->
                    join('reserved_seats', 'reserved_seats.pch_id', '=', 'purchases.id')->
                    leftJoin('daily_tickets', 'daily_tickets.purchase_id', '=', 'purchases.id')->
                    whereIn('purchases.ticket_id', array_keys($seatNumbers))->
                    whereIn('reserved_seats.seat_number', array_unique(array_reduce($seatNumbers, function ($last, $curr) {
                    if (! $last) {
                        return $curr;
                    }
                    return array_merge($last, $curr);
                })))->
                    where(function ($query) use ($visitDates) {
                    $query->whereIn('daily_tickets.visit_date', array_unique(array_reduce($visitDates, function ($last, $curr) {
                        if (! $last) {
                            return $curr;
                        }
                        return array_merge($last, $curr);
                    })))->orWhereNull('daily_tickets.id');
                })->
                    get()->toArray();
            }
            foreach ($seatNumbers as $key => $value) {
                if (! array_key_exists($key, $seatNumberTickets) || count($value) == 0 || ! is_array($value)) {
                    return ["error" => "Seat number is can't blank if you choose a ticket with seat number option", "code" => 403];
                }
                if (count($value) < $seatNumberTickets[$key] || (array_key_exists($key, $dailyTickets) && count($visitDates[$key]) > count($value))) {
                    return ["error" => "Count of seat nummber must be same as total ticket have selected", "code" => 403];
                }
                // Check duplicated seat number every same date if visitdate available
                if (array_key_exists($key, $dailyTickets)) {
                    foreach (array_count_values($visitDates[$key]) as $val => $count) {
                        if ($count > 1) {
                            $duplicateDatesIndex = array_keys($visitDates[$key], $val);
                            for ($i = 0; $i < count($duplicateDatesIndex); $i++) {
                                for ($j = 0; $j < count($duplicateDatesIndex); $j++) {
                                    if ($value[$duplicateDatesIndex[$i]] === $value[$duplicateDatesIndex[$j]] && $i !== $j) {
                                        return ["error" => "You can't reserved same seat number in one ticket on same time / date", "code" => 403];
                                    }
                                }
                            }
                        }
                    }
                } else if (count($value) != count(array_unique($value))) {
                    return ["error" => "You can't reserved same seat number in one ticket on same time / date", "code" => 403];
                }
                for ($i = 0; $i < $seatNumberTickets[$key]; $i++) {
                    $hasPurchased = null;
                    if (array_key_exists($key, $visitDates)) {
                        if ($value[$i] <= 0 || $value[$i] > intval($seatNumberTicketsData[$key]->limitDaily->limit_quantity)) {
                            return ["error" => "Seat number is only available beetwen 1 to " . $seatNumberTicketsData[$key]->limitDaily->limit_quantity, "code" => 404];
                        }
                        $dateFormat   = new DateTime($visitDates[$key][$i], new DateTimeZone('Asia/Jakarta'));
                        $hasPurchased = array_filter($hasPurchasedRaw, function ($val) use ($dateFormat, $key, $value, $i) {
                            $d = new DateTime($val->visitDate, new DateTimeZone('Asia/Jakarta'));
                            return $d->format('Y-m-d') == $dateFormat->format('Y-m-d') && $val->ticketId == $key && $val->seatNumber == $value[$i];
                        });
                    } else {
                        if ($value[$i] <= 0 || $value[$i] > intval($seatNumberTicketsData[$key]->quantity)) {
                            return ["error" => "Seat number is only available beetwen 1 to " . $seatNumberTicketsData[$key]->quantity, "code" => 404];
                        }
                        $hasPurchased = array_filter($hasPurchasedRaw, function ($val) use ($key, $value, $i) {
                            return $val->ticketId == $key && $val->seatNumber == $value[$i];
                        });
                    }
                    if (count($hasPurchased) > 0) {
                        return ["error" => "This seat number has reserved. Please choose other seat number", "code" => 404];
                    }
                }
            }
        }
        return ["seatNumbers" => $seatNumbers];
    }

    private function basicCreateData($req, $ticket_ids, $ticket_datas, $visitDates, $customPrices, $seatNumbers, $voucher, $payment, $remainingVoucher, bool $forOrgInv = false)
    {
        /*
        ==================================================================================
        Status code :
        - (0) => failed
        - (1) => success
        ==================================================================================
         */

        $totalPay          = 0;
        $netTotal          = 0;
        $taxTotal          = 0;
        $purchases         = [];
        $pchIds            = [];
        $visitDatesIns     = [];
        $visitDatesInsIds  = [];
        $seatNumbersIns    = [];
        $seatNumbersInsIds = [];
        $profitSetting     = ProfitSetting::first();
        $vcTickets         = $req->voucher_code ? $voucher->forTickets()->get() : [];
        $ticketsValue      = [];
        $strWhenSql        = "case ";
        $onlyTicketId      = [];
        $outIndex          = 0;
        foreach ($ticket_ids as $key => $value) {
            $spcVc = false;
            foreach ($vcTickets as $vcTicket) {
                if ($vcTicket->ticket_id === $key) {
                    $spcVc = true;
                }
            }
            if ($forOrgInv == false) {
                $strWhenSql .= ("when id='" . $key . "' then '" . (intval($ticket_datas[$outIndex]->quantity) - $value) . "' ");
            }
            array_push($onlyTicketId, $key);
            for ($i = 0; $i < $value; $i++) {
                $amount      = 0;
                $voucherCode = '-';
                $priceTicket = $ticket_datas[$outIndex]->type_price == 3 ? $customPrices[$key][$i] : $ticket_datas[$outIndex]->price;
                if ($forOrgInv == true) {
                    $amount = 0;
                } else if ($req->voucher_code) {
                    if ($voucher->event_id == $ticket_datas[$outIndex]->event_id && $remainingVoucher > 0 && (count($vcTickets) == 0 || ($spcVc))) {
                        $amount      = (intval($priceTicket) - ($voucher->discount > 1 ? intval($voucher->discount) : (intval($priceTicket) * floatval($voucher->discount))));
                        $voucherCode = $req->voucher_code;
                        $remainingVoucher -= 1;
                    } else {
                        $amount = intval($priceTicket);
                    }
                } else {
                    $amount = intval($priceTicket);
                }
                $taxAmount = $amount * $profitSetting->tax_fee;
                $totalPay += ($amount + $taxAmount);
                $netTotal += $amount;
                $taxTotal += $taxAmount;
                $pchId = Str::uuid();
                array_push($pchIds, $pchId);
                array_push($purchases,
                    [
                        'id'         => $pchId,
                        'user_id'    => Auth::user()->id,
                        'pay_id'     => $payment->id,
                        'ticket_id'  => $key,
                        'amount'     => $amount,
                        'tax_amount' => $taxAmount,
                        'code'       => $forOrgInv == true ? "-" : $voucherCode,
                        'is_mine'    => true,
                        'org_inv'    => $forOrgInv,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                if (array_key_exists($key, $visitDates)) {
                    $visitDate = new DateTime($visitDates[$key][$i], new DateTimeZone('Asia/Jakarta'));
                    array_push($visitDatesInsIds, Str::uuid());
                    array_push($visitDatesIns, [
                        "id"          => $visitDatesInsIds[count($visitDatesInsIds) - 1],
                        "purchase_id" => $pchId,
                        "visit_date"  => $visitDate->format('Y-m-d'),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
                if (array_key_exists($key, $seatNumbers)) {
                    array_push($seatNumbersInsIds, Str::uuid());
                    array_push($seatNumbersIns, [
                        "id"          => $seatNumbersInsIds[count($seatNumbersInsIds) - 1],
                        "pch_id"      => $pchId,
                        "seat_number" => $seatNumbers[$key][$i],
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
            $outIndex++;
        }

        if ($strWhenSql == "case ") {
            $strWhenSql = null;
        } else {
            $strWhenSql .= " end";
        }

        try {
            Purchase::insert($purchases);
        } catch (\Throwable $th) {
            return ["status" => 0];
        }
        try {
            if (count($visitDatesIns) > 0) {
                DailyTicket::insert($visitDatesIns);
            }
            if (count($seatNumbersIns) > 0) {
                ReservedSeat::insert($seatNumbersIns);
            }
        } catch (\Throwable $th) {
            return ["status" => 0];
        }
        // update quantity ticket
        try {
            if ($strWhenSql != null && $forOrgInv == false) {
                Ticket::whereIn('id', $onlyTicketId)->where('quantity', '!=', -1)->update(
                    [
                        "quantity" => DB::raw($strWhenSql),
                    ]
                );
            }
        } catch (\Throwable $th) {
            $this->rollbackPurchase($ticket_ids, $ticket_datas, $payment, $forOrgInv);
            return [
                "status" => 0,
                "msg"    => "Deatchlock timeout",
            ];
        }

        $returnData = [
            "netTotal"       => $netTotal,
            "taxTotal"       => $taxTotal,
            "totalPay"       => $totalPay,
            "purchases"      => $purchases,
            "profitSetting"  => $profitSetting,
            "visitDatesIns"  => $visitDatesIns,
            "seatNumbersIns" => $seatNumbersIns,
            "status"         => 1,
        ];

        return $returnData;
    }

    public function create(Request $req, bool $forOrg = false)
    {
        $this->loadTrxData();
        $validator = Validator::make(
            $req->all(),
            [
                'ticket_ids' => 'required',
            ]
        );
        if ($validator->fails() && ! isset($req->ticket_ids)) {
            return response()->json(["error" => "Please select one ticket or more for doing a transaction"], 403);
        }
        if ((intval($req->pay_method) == 14 && ! $req->mobile_number) || (intval($req->pay_method) == 15 && ! $req->cashtag)) {
            return response()->json(["error" => "mobile number is required for pay method with OVO or $" . "cashtag is required for pay method with JeniusPay"], 403);
        }
        $errorValidator = [
            "status" => false,
            "data"   => null,
        ];
        DB::beginTransaction();
        try {
            $voucher = Voucher::where('code', $req->voucher_code)->lockForUpdate()->first();
            if ($req->voucher_code && ! $voucher) {
                throw new \Exception(json_encode(["error" => "Voucher code not found", "code" => 404]));
            }
            $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));

            $validatorVouchers = $this->voucherValidator($req, $voucher, $now);
            if (array_key_exists("error", $validatorVouchers)) {
                throw new \Exception(json_encode($validatorVouchers));
            }
            $remainingVoucher = $validatorVouchers["remainingVoucher"];
            $ticket_ids       = array_count_values($req->ticket_ids);

            $basicvalidator = $this->basicValidator($req, $ticket_ids, $now, $forOrg);
            if (array_key_exists("error", $basicvalidator)) {
                throw new \Exception(json_encode($basicvalidator));
            }
            $allTicket          = $basicvalidator["all_ticket"];
            
            $customPriceTickets = $basicvalidator["customPriceTickets"];
            $dailyTickets       = $basicvalidator["dailyTickets"];
            $seatNumberTickets  = $basicvalidator["seatNumberTickets"];

            $validatorCustomPrices = $this->customPriceValidator($req, $customPriceTickets);
            if (array_key_exists("error", $validatorCustomPrices)) {
                throw new \Exception(json_encode($validatorCustomPrices));
            }
            $customPrices = $validatorCustomPrices["customPrices"];

            $validatorVisitDates = $this->visitDatesValidator($req, $dailyTickets, $basicvalidator["dailyTicketsData"], $now, $forOrg);
            if (array_key_exists("error", $validatorVisitDates)) {
                throw new \Exception(json_encode($validatorVisitDates));
            }
            $visitDates = $validatorVisitDates["visitDates"];

            $validatorSeatNumbers = $this->seatNumbersValidator($req, $seatNumberTickets, $basicvalidator["seatNumberTicketsData"], $dailyTickets, $visitDates);
            if (array_key_exists("error", $validatorSeatNumbers)) {
                throw new \Exception(json_encode($validatorSeatNumbers));
            }
            $seatNumbers = $validatorSeatNumbers["seatNumbers"];
            // create trx dummy and get the ID

            $payment = Payment::create(
                [
                    'user_id'      => Auth::user()->id,
                    'token_trx'    => '-',
                    'pay_state'    => 'PENDING',
                    'order_id'     => '-',
                    'price'        => 0,
                    'admin_fee'    => 0,
                    'platform_fee' => 0,
                ]
            );
            // return response()->json($this->basicCreateData($req, $ticket_ids, $visitDates, $customPrices, $seatNumbers, $voucher, $payment, $remainingVoucher));
            $mainCreateData = $this->basicCreateData($req, $ticket_ids, $basicvalidator["all_ticket"], $visitDates, $customPrices, $seatNumbers, $voucher, $payment, $remainingVoucher, $forOrg);
            
            if ($mainCreateData["status"] === 0) {
                Payment::where('id', $payment->id)->delete();
                return response()->json(["error" => isset($mainCreateData["msg"]) ? $mainCreateData["msg"] : "Duplicated UUID"], 500);
            }
            $totalPay      = $mainCreateData["totalPay"];
            $profitSetting = $mainCreateData["profitSetting"];
            $purchases     = $mainCreateData["purchases"];
            $netTotal      = $mainCreateData["netTotal"];
            $taxTotal      = $mainCreateData["taxTotal"];
            if ($totalPay < 10000 && $totalPay > 0) {
                $this->rollbackPurchase($ticket_ids, $basicValidator["all_ticket"], $payment, $forOrg);
                return response()->json(["error" => "Sorry, minimal transaction (for non-free ticket) is IDR Rp. 10.000,-. Please remove the voucher code first"], 403);
            }
            // change trx data
            $paymentXendit = null;
            if ($forOrg) {
                $orderId = uniqid('trx_free', true);
                Payment::where('id', $payment->id)->update(
                    [
                        'token_trx' => '-',
                        'pay_state' => "SUCCEEDED",
                        'order_id'  => $orderId,
                        'price'     => 0,
                    ]
                );
                $paymentXendit["payment"] = Payment::where('id', $payment->id)->first();
            } else if ($totalPay == 0) {
                $orderId = uniqid('trx_free', true);
                Payment::where('id', $payment->id)->update(
                    [
                        'token_trx' => '-',
                        'pay_state' => "SUCCEEDED",
                        'order_id'  => $orderId,
                        'price'     => 0,
                    ]
                );
                $paymentXendit["payment"] = Payment::where('id', $payment->id)->first();
                try {
                    Mail::to($paymentXendit["payment"]->user()->first()->email)->send(new ETicket($payment->id));
                } catch (\Throwable $th) {
                    $this->rollbackPurchase($ticket_ids, $basicValidator["all_ticket"], $payment, $forOrg);
                    Log::info("Error With Mail Server : Failed send mail transaction. Transaction reset");
                    return response()->json(["error" => "Mail server error. Please try again later"], 500);
                }
            } else {
                try {
                    if (intval($req->pay_method) >= 11 && intval($req->pay_method) <= 15) {
                        $paymentXendit = $this->setTrxEWallet($payment->id, $req->pay_method, $totalPay, $taxTotal, $profitSetting, $req->mobile_number, $req->cashtag);
                    } else if (intval($req->pay_method) >= 21 && intval($req->pay_method) <= 22) {
                        $paymentXendit = $this->setTrxQris($payment->id, $req->pay_method, $totalPay, $taxTotal, $profitSetting);
                    } else if (intval($req->pay_method) >= 31 && intval($req->pay_method) <= 41) {
                        $paymentXendit = $this->setTrxVirAccount($payment->id, $req->pay_method, $totalPay, $profitSetting);
                    } else {
                        $paymentXendit = $this->setTrxVirAccount($payment->id, $req->pay_method, $totalPay, $profitSetting);
                    }
                } catch (\Throwable $th) {
                    $this->rollbackPurchase($ticket_ids, $allTicket, $payment, $forOrg);
                    Log::info($th);
                    return response()->json(["error" => "Server error. Failed reach xendit server", "msg" => $th], 500);
                }
                try {
                    Mail::to(Auth::user()->email)->send(new TrxNotification($payment->id));
                } catch (\Throwable $th) {
                    $this->rollbackPurchase($ticket_ids, $allTicket, $payment, $forOrg);
                    Log::info("Error With Mail Server : Failed send mail transaction. Transaction reset");
                    return response()->json(["error" => "Mail server error. Please try again later"], 500);
                }
            }
            
            DB::commit();

            return response()->json(
                [
                    "local_pay_id"   => $payment->id,
                    "payment"        => $paymentXendit["payment"],
                    "purchases"      => $purchases,
                    "netTotal"       => $netTotal,
                    "taxTotal"       => $taxTotal,
                    "adminFeeTrx"    => $profitSetting->admin_fee_trx,
                    "platformFee"    => array_key_exists("platform", $paymentXendit) ? $paymentXendit["platform"] : 0,
                    "total"          => array_key_exists("total", $paymentXendit) ? $paymentXendit["total"] : 0,
                    "visitDatesIns"  => $forOrg ? $mainCreateData["visitDatesIns"] : null,
                    "seatNumbersIns" => $forOrg ? $mainCreateData["seatNumbersIns"] : null,
                ],
                201
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info($th);
            $error = json_decode($th->getMessage(), true);
            if ($error) {
                return response()->json($error, $error["code"] ?? 500);
            }
            return response()->json(["error" => "Database is busy. Please try again later"], 500);
        }
    }

    private function validationPurchase($req, $forRefund = false, $userData = null)
    {
        $user     = $userData == null ? Auth::user() : $userData;
        $purchase = Purchase::where('id', $req->purchase_id)->where('user_id', $user->id)->first();
        if (! $purchase) {
            return ["error" => "Purchase data not found", "code" => 404];
        }
        if ($purchase->amount != 0 && $purchase->payment()->first()->pay_state != 'SUCCEEDED') {
            return ["error" => "Purchase is not valid or payment is failed", "code" => 403];
        }
        if ($purchase->checkin()->first()) {
            return ["error" => "You have used this ticket, with identified by your checkin data", "code" => 403];
        }
        $ticket = Ticket::where('id', $purchase->ticket_id)->first();
        $event  = $ticket->event()->first();
        if ($event->category != 'Attraction' && $event->category != 'Daily Activities' && $event->category != 'Tour Travel (recurring)' && $ticket->seat_number == false && $forRefund == false) {
            return ["error" => "Tickets for this event (which you have purchased) do not have a re-schedule feature", "code" => 403];
        }
        $pchVisitDate = $purchase->visitDate()->first();
        $now          = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        if ($pchVisitDate && $forRefund === false) {
            $visitDate   = new DateTime($pchVisitDate->visit_date, new DateTimeZone('Asia/Jakarta'));
            $limitTime   = $event->availableDays()->where('day', $visitDate->format('D'))->first();
            $limitChange = new DateTime($pchVisitDate->visit_date . ' ' . $limitTime->max_limit_time, new DateTimeZone('Asia/Jakarta'));
            if ($now > $limitChange) {
                return ["error" => "You can't change visit date if this ticket has expired", "code" => 403];
            }
        }
        $endEvent = new DateTime($event->end_date . ' ' . $event->end_time, new DateTimeZone('Asia/Jakarta'));
        if ($now > $endEvent && ($event->category != 'Attraction' && $event->category != 'Daily Activities' && $event->category != 'Tour Travel (recurring)') && $event->deleted === 0) {
            return ["error" => "You can't refund / reschedule if the event has ended", "code" => 403];
        }
        return [
            "ticket"   => $ticket,
            "event"    => $event,
            "purchase" => $purchase,
        ];
    }

    public function reSchedule(Request $req)
    {
        $resValidate = $this->validationPurchase($req);
        if (array_key_exists("error", $resValidate)) {
            return response()->json(["error" => $resValidate["error"]], $resValidate["code"]);
        }
        $availableRsc = $resValidate["event"]->availableReschedule()->first();
        if (! $availableRsc) {
            return response()->json(["message" => "This ticket not have permission for doing Re-Schedule"], 403);
        }
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        // ============== Check limit re-schedule ===================
        $endDate = null;
        if ($resValidate["ticket"]->quantity == -1) {
            $endDate = $resValidate["purchase"]->visitDate()->first()->visit_date;
        } else {
            $endDate = $resValidate["event"]->end_date;
        }
        $endDate = new DateTime($endDate, new DateTimeZone('Asia/Jakarta'));
        $endDate = $endDate->sub(new DateInterval('P' . $availableRsc->limit_time . 'D'));
        if ($now->format('Y-m-d') > $endDate->format('Y-m-d')) {
            return response()->json(["error" => "You can't doing re-schedule if over from max limit re-schedule time (H-" . $availableRsc->limit_time . ")"], 403);
        }
        // ==========================================================
        $eventCtrl = new EventCtrl();
        $date      = null;
        $strDate   = $req->visit_date ? $req->visit_date : 'now';
        try {
            $date = new DateTime($strDate, new DateTimeZone('Asia/Jakarta'));
            if ($date->format('Y-m-d') < $now->format('Y-m-d')) {
                return response()->json(["error" => "Invalid seleceted date, selected visit date must be greather equal than now"], 403);
            }
        } catch (\Throwable $th) {
            return response()->json(["error" => "Invalid date format"], 403);
        }
        $ticket         = $eventCtrl->coreSeatNumberQtyTicket($resValidate["ticket"], $date);
        $passSeatNumber = false;
        $passVisitDate  = false;
        if ($req->seat_number && $ticket->seat_number == true) {
            if (! in_array(intval($req->seat_number), $ticket->available_seat_numbers)) {
                return response()->json(["error" => "The seat number you selected is not available"], 404);
            }
            $passSeatNumber = true;
        }
        if (
            $req->visit_date
            && ($resValidate["event"]->category == 'Attraction' || $resValidate["event"]->category == 'Daily Activities' || $resValidate["event"]->category == 'Tour Travel (recurring)')
        ) {
            $avlSelectedDay = $resValidate["event"]->availableDays()->where('day', $date->format('D'))->get();
            if (count($avlSelectedDay) == 0) {
                return response()->json(["error" => "The day has you selected, is not available", 404]);
            }
            $timeLimit = new DateTime($avlSelectedDay[0]->max_limit_time, new DateTimeZone('Asia/Jakarta'));
            if ($now->format('Y-m-d') == $date->format('Y-m-d') && $now->format('H:i:s') > $timeLimit->format('H:i:s')) {
                return response()->json(["error" => "Limit open time in your choosen day has reached"], 403);
            }
            if ($ticket->quantity == 0) {
                return response()->json(["error" => "Limit quantity in your choosen day has reached"], 403);
            }
            $passVisitDate = true;
        }
        if ($passSeatNumber) {
            ReservedSeat::where('pch_id', $req->purchase_id)->update(
                [
                    'seat_number' => $req->seat_number,
                ]
            );
        }
        if ($passVisitDate) {
            DailyTicket::where('purchase_id', $req->purchase_id)->update(
                [
                    'visit_date' => $date->format('Y-m-d'),
                ]
            );
        }
        $req->pch_id = $req->purchase_id;
        return $this->get($req);
    }

    public function submitRefund(Request $req)
    {
        if (! $req->message) {
            return response()->json(["error" => "Message field is required for admin consideration"], 403);
        }
        if (! $req->phone_number) {
            return response()->json(["error" => "Phone number field is required for admin consideration"], 403);
        }
        if (! $req->account_number) {
            return response()->json(["error" => "Account number / VA number field is required for admin consideration"], 403);
        }
        if (! $req->bank_code) {
            return response()->json(["error" => "Bank code field is required for admin consideration"], 403);
        }
        if (! $req->account_name) {
            return response()->json(["error" => "Account name field is required for admin consideration"], 403);
        }
        if (! array_key_exists($req->bank_code, config('banks'))) {
            return response()->json(["error" => "Bank code not available"], 404);
        }

        $purchase = Purchase::where('id', $req->purchase_id)->first();
        if (! $purchase) {
            return response()->json(["error" => "Purchase data not found"], 404);
        }

        $pchs  = [];
        $event = $purchase->ticket()->first()->event()->first();

        if ($event->deleted === 0) {
            if ($event->allow_refund == 1 && ! RefundData::where('purchase_id', $purchase->id)->first()) {
                $resValidate = $this->validationPurchase($req, true);
                if (! array_key_exists("error", $resValidate)) {
                    array_push($pchs, $resValidate);
                }
            }
        } else {
            foreach ($purchase->payment()->first()->purchases()->get() as $purchaseInner) {
                $req->purchase_id = $purchaseInner->id;
                if ($purchaseInner->event()->first()->allow_refund == 1 && ! RefundData::where('purchase_id', $purchaseInner->id)->first()) {
                    $resValidate = $this->validationPurchase($req, true);
                    if (! array_key_exists("error", $resValidate)) {
                        array_push($pchs, $resValidate);
                    }
                }
            }
        }

        $user             = Auth::user();
        $refundPercentage = 0;
        if ($resValidate["event"]->deleted === 1) {
            $refundSettingDef = RefundSetting::where('day_before', -1)->first();
            if ($refundSettingDef) {
                $refundPercentage = $refundSettingDef->allow_refund;
            } else {
                $refundPercentage = 1;
            }
        } else {
            $visitDate = $resValidate['purchase']->visitDate()->first();
            $start     = null;
            $now       = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            if ($visitDate) {
                $start = new DateTime($visitDate->visit_date, new DateTimeZone('Asia/Jakarta'));
            } else {
                $start = new DateTime($resValidate["event"]->start_date, new DateTimeZone('Asia/Jakarta'));
            }
            if ($now > $start) {
                $refundPercentage = 0;
            } else {
                $diff             = date_diff($start, $now)->days * 24 + date_diff($start, $now)->h;
                $refundSetting    = RefundSetting::where('day_before', '>=', $diff)->orderBy('day_before', 'ASC')->first();
                $refundPercentage = $refundSetting ? $refundSetting->allow_refund : 1;
            }
        }
        foreach ($pchs as $pch) {
            RefundData::create(
                [
                    "purchase_id"    => $pch["purchase"]->id,
                    "user_id"        => $user->id,
                    "ticket_id"      => $pch["ticket"]->id,
                    "event_id"       => $pch["event"]->id,
                    "message"        => $req->message,
                    "phone_number"   => $req->phone_number,
                    "bank_code"      => $req->bank_code,
                    "account_name"   => $req->account_name,
                    "account_number" => $req->account_number,
                    "percentage"     => $refundPercentage,
                    "nominal"        => ceil($pch["purchase"]->amount * $refundPercentage),
                    "basic_nominal"  => $pch["purchase"]->amount,
                ]
            );
        }
        $mail_status = true;
        if (count($pchs) > 0) {
            try {
                Mail::to(config('agendakota.admin_email'))->send(
                    new AdminRefundNotification(
                        $user->name,
                        $user->email,
                        $pchs[0]["event"]->name,
                        $pchs[0]["purchase"]->id,
                        $pchs[0]["ticket"]->name,
                        $pchs[0]["ticket"]->id,
                        $req->message
                    )
                );
                Mail::to($pchs[0]["event"]->org()->first()->user()->first()->email)->send(
                    new AdminRefundNotification(
                        $user->name,
                        $user->email,
                        $pchs[0]["event"]->name,
                        $pchs[0]["purchase"]->id,
                        $pchs[0]["ticket"]->name,
                        $pchs[0]["ticket"]->id,
                        $req->message
                    )
                );
            } catch (\Throwable $th) {
                ResendTrxNotification::writeErrorLog('App\Mail\AdminRefundNotification', "Refund Notification", [
                    $user->name,
                    $user->email,
                    $pchs[0]["event"]->name,
                    $pchs[0]["purchase"]->id,
                    $pchs[0]["ticket"]->name,
                    $pchs[0]["ticket"]->id,
                    $req->message,
                ], config('agendakota.admin_email'), $pchs[0]["event"]->org()->first()->user()->first()->email);
                $mail_status = false;
            }
        } else {
            return response()->json(["error" => "Haven't valid purchase for refund"], 404);
        }

        // add notify emeil to organizer
        return response()->json(["message" => "Your refund requets have sent. Check you email, for view your refund status", "mail_status" => $mail_status], 201);
    }

    public function getRefunds(Request $req, $admin = false)
    {
        $refundDatas = $admin ? RefundData::all() : RefundData::where('event_id', $req->event->id)->get();
        foreach ($refundDatas as $refundData) {
            $refundData->user     = $refundData->user()->first();
            $refundData->purchase = $refundData->purchase()->first();
            $refundData->ticket   = $refundData->ticket()->first();
            $refundData->event    = $refundData->event()->first();
            // $refundData->status = $refundData->purchase ? 'Un Approved' : 'Approved';
        }
        return response()->json(["refund_datas" => $refundDatas], 200);
    }

    public function getRefundsOrg(Request $req)
    {
        return $this->getRefunds($req, false);
    }

    public function getRefund(Request $req, $refundId, $admin = false)
    {
        $refundData = $admin ? RefundData::where('id', $refundId)->first() : RefundData::where('id', $refundId)->where('event_id', $req->event->id)->first();
        if (! $refundData) {
            return response()->json(["error" => "Refund data not found"], 404);
        }
        $refundData->user     = $refundData->user()->first();
        $refundData->purchase = $refundData->purchase()->first();
        $refundData->ticket   = $refundData->ticket()->first();
        $refundData->event    = $refundData->event()->first();
        // $refundData->status = $refundData->purchase ? 'Un Approved' : 'Approved';
        return response()->json(["refund_data" => $refundData], 200);
    }

    public function getRefundOrg(Request $req, $refundId)
    {
        return $this->getRefund($req, $refundId, false);
    }

    private function createDisburstment($disburstment)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.xendit.co/disbursements',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($disburstment),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-IDEMPOTENCY-KEY: ' . time(),
                'Authorization: Basic ' . base64_encode(env('XENDIT_API_WRITE') . ':'),
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response);
    }

    public function considerationRefundMain(Request $req, $refundDatas = [], $admin = false, $setManualFinish = false)
    {

        /*
        TERM CONDITION REFUND PERCENTAGE
        1. If refund because canceling event, percentage refund follow parameter -1 in column 'day_before' on table refund_table, or five 100% if parameter -1 not found
        2. If refund is personal by user, percentage depend by different day before event start / selected visit date. With different parameter in colum 'day_before' on table refund_table.
         */

        $user          = null;
        $nominal       = 0;
        $strRefundId   = '';
        $eventNames    = ''; // for external_id disburstment
        $ticketNames   = ''; // for description disburstment
        $ticketStrIds  = ''; // for description disburstment
        $errorMessages = [];
        foreach ($refundDatas as $refundData) {
            $user             = $refundData->user()->first();
            $req->purchase_id = $refundData->purchase_id;
            // array_push($purchaseIds, $req->purchase_id);
            $resValidate = $this->validationPurchase($req, true, $user);
            if (array_key_exists("error", $resValidate)) {
                array_push($errorMessages, $resValidate["error"]);
            } else {
                if (! $req->approved && $admin) {
                    RefundData::where('id', $refundData->id)->delete();
                } else if (! $req->approved) {
                    RefundData::where('id', $refundData->id)->update(["approve_org" => false]);
                } else {

                    RefundData::where('id', $refundData->id)->update($admin && $resValidate["event"]->deleted === 1 ? [
                        "approve_admin" => true,
                        "approve_org"   => true,
                        "mode"          => $setManualFinish ? "manual" : "auto",
                    ] : ($admin ? [
                        "approve_admin" => true,
                        "mode"          => $setManualFinish ? "manual" : "auto",
                    ] : [
                        "approve_org" => true,
                    ]));
                    $nominal += RefundData::where('id', $refundData->id)->first()->nominal;
                    if ($setManualFinish && $admin) {
                        Purchase::where('id', $refundData->purchase_id)->delete();
                    }
                }
                $strRefundId .= $refundData->id . '~^&**&^~';
                $eventNames .= ($resValidate["event"]->name . ', ');   // for external_id disburstment
                $ticketNames .= ($resValidate["ticket"]->name . ', '); // for description disburstment
                $ticketStrIds .= ($resValidate["ticket"]->id . ', ');  // for description disburstment
            }
        }
        $refundData  = count($refundDatas) > 0 ? $refundDatas[count($refundDatas) - 1] : null;
        $mail_status = true;
        if (! $req->approved && $strRefundId !== '') {
            try {
                Mail::to($user->email)->send(
                    new UserRefundNotification(
                        'Un Approved / Rejected',
                        $eventNames,
                        $refundData->purchase_id,
                        $ticketNames,
                        $ticketStrIds,
                        $refundData->message
                    )
                );
            } catch (\Throwable $th) {
                ResendTrxNotification::writeErrorLog('App\Mail\UserRefundNotification', "User Refund Notification", [
                    'Un Approved / Rejected',
                    $eventNames,
                    $refundData->purchase_id,
                    $ticketNames,
                    $ticketStrIds,
                    $refundData->message,
                ], $user->email);
                $mail_status = false;
            }
        } else if ($req->approved && $admin && $strRefundId !== '' && ! $setManualFinish) {
            $uniqueExternal    = uniqid('external_refund_', true);
            $localDisburstment = DisburstmentRefund::create([
                'disburstment_id' => $uniqueExternal,
                'str_refund_ids'  => $strRefundId,
            ]);
            $res = $this->createDisburstment([
                "external_id"         => $uniqueExternal,
                "amount"              => ($nominal - intval(config('payconfigs.payout_fee'))),
                "bank_code"           => $refundDatas[0]->bank_code,
                "account_holder_name" => $refundDatas[0]->account_name,
                "account_number"      => $refundDatas[0]->account_number,
                "description"         => "Refund payment from event (" . $eventNames . ") and ticket (" . $ticketNames . ")",
            ]);
            // array_push($resOut, $res);
            if (isset($res->error_code)) {
                foreach ($refundDatas as $refundData) {
                    RefundData::where('id', $refundData->id)->update([
                        "approve_admin" => false,
                    ]);
                }

                DisburstmentRefund::where('id', $localDisburstment->id)->delete();
                array_push($errorMessages, "Failed reach xendit server");
            } else {
                try {
                    Mail::to($user->email)->send(
                        new UserRefundNotification(
                            'Approved / Accepted',
                            $eventNames,
                            $refundData->purchase_id,
                            $ticketNames,
                            $ticketStrIds,
                            $refundData->message
                        )
                    );
                } catch (\Throwable $th) {
                    ResendTrxNotification::writeErrorLog('App\Mail\UserRefundNotification', "User Refund Notification", [
                        'Approved / Accepted',
                        $eventNames,
                        $refundData->purchase_id,
                        $ticketNames,
                        $ticketStrIds,
                        $refundData->message,
                    ], $user->email);
                    $mail_status = false;
                }
            }
        } else if ($req->approved && $admin && $strRefundId !== '' && $setManualFinish) {
            try {
                Mail::to($user->email)->send(
                    new UserRefundNotification(
                        'Approved / Accepted',
                        $eventNames,
                        $refundData->purchase_id,
                        $ticketNames,
                        $ticketStrIds,
                        $refundData->message
                    )
                );
            } catch (\Throwable $th) {
                ResendTrxNotification::writeErrorLog('App\Mail\UserRefundNotification', "User Refund Notification", [
                    'Approved / Accepted',
                    $eventNames,
                    $refundData->purchase_id,
                    $ticketNames,
                    $ticketStrIds,
                    $refundData->message,
                ], $user->email);
                $mail_status = false;
            }
        }

        return ["errors" => $errorMessages, "mail_status" => $mail_status];
    }

    public function considerationRefund(Request $req)
    {
        $refundData = RefundData::where('id', $req->id)->first();
        // Filter refund data
        if (! $refundData) {
            return response()->json(["error" => "Data Refund tidak dapat ditemukan"], 404);
        }
        if ($refundData->approve_admin == true) {
            return response()->json(["error" => "Data Refund sudah di approve oleh admin"], 403);
        }
        if ($refundData->event()->first()->deleted === 1) {
            return response()->json(["error" => "Event telah dibatalkan, organizer tidak memiliki wewenang untuk mengatur data refund"], 403);
        }
        $res = $this->considerationRefundMain($req, [$refundData]);
        return response()->json(count($res["errors"]) === 0 ? ["message" => "Refund dari purchase ID " . $refundData->purchase_id . " berhasil diubah statusnya", "mail_status" => $res["mail_status"]] : ["message" => "Refund gagal dilakukan karena tiket sudah tidak valid atau sudah checkin", "mail_status" => $res["mail_status"]], count($res["errors"]) === 0 ? 202 : 403);
    }

    public function setFinishRefund($refundIds)
    {
        if (! is_array($refundIds)) {
            return response()->json(["error" => "refund ids is an array"], 403);
        }
        $refundDatas = [];
        foreach ($refundIds as $refundId) {
            $refundData = RefundData::where('id', $refundId)->first();
            if (! $refundData) {
                return response()->json(["error" => "Refund data not found"], 404);
            }
            if ($refundData->purchase()->first() || $refundData->approve_admin == false) {
                return response()->json(["error" => "This data not yet approved. Please approve first, to set finish state"], 403);
            }
            array_push($refundDatas, $refundData);
        }
        foreach ($refundDatas as $refundData) {
            RefundData::where('id', $refundData->id)->update([
                'finish' => true,
            ]);
        }
        return response()->json(["message" => "Refund data has set to fisnish transfer"], 202);
    }

    private function loadTrxValidation($paymentData)
    {
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $exp = new DateTime($paymentData->expired, new DateTimeZone('Asia/Jakarta'));
        $exp = $exp->add(new DateInterval('PT1M'));
        if ($exp < $now) {
            $purchases = $paymentData->purchases()->get()->groupBy('ticket_id');
            foreach ($purchases as $key => $value) {
                Ticket::where('id', $key)->where('type_price', '!=', 1)->where('quantity', '!=', -1)->update(
                    [
                        'quantity' => intval($value[0]->ticket()->first()->quantity) + count($value),
                    ]
                );
                foreach ($value as $pch) {
                    if ($pch->amount != 0) {
                        DailyTicket::where('purchase_id', $pch->id)->delete();
                        ReservedSeat::where('pch_id', $pch->id)->delete();
                        Purchase::where('id', $pch->id)->update(["code" => '-']);
                    }
                }
            }
            Payment::where('id', $paymentData->id)->update(
                [
                    'pay_state' => "EXPIRED",
                ]
            );
            $paymentData->pay_state = "EXPIRED";
        }
        return $paymentData;
    }

    // This function to purchase by id
    public function get(Request $req)
    {
        $user     = Auth::user();
        $purchase = Purchase::where('id', $req->pch_id)->where('user_id', $user->id)->first();
        if (! $purchase) {
            return response()->json(["error" => "This purchase is not found"], 404);
        }
        $purchase->ticket = $purchase->ticket()->first();
        if ($purchase->ticket->quantity == -1) {
            $purchase->ticket->quantity = $purchase->ticket->limitDaily()->first()->limit_quantity;
        }
        $purchase->ticket->event                       = $purchase->ticket->event()->first();
        $purchase->ticket->event->available_days       = $purchase->ticket->event->availableDays()->get();
        $purchase->ticket->event->available_reschedule = $purchase->ticket->event->availableReschedule()->first();
        $purchase->ticket->event->org                  = $purchase->ticket->event->org()->first();
        $purchase->ticket->event->org->legality        = $purchase->ticket->event->org->credibilityData()->first();

        $purchase->visitDate   = $purchase->visitDate()->first();
        $purchase->seat_number = $purchase->seatNumber()->first();
        $payData               = null;
        if ($purchase->payment()->first()->pay_state == 'PENDING') {
            $payData = $this->loadTrxValidation($purchase->payment()->first());
        } else {
            $payData       = $purchase->payment()->first();
            $payData->user = $user;
        }
        $ticket = $purchase->ticket()->first();
        return response()->json(
            [
                "purchase"    => $purchase,
                'secret_info' => $ticket->secretInfo()->first(),
                "payment"     => $payData,
                "qr_str"      => $purchase->id . "*~^|-|^~*" . $user->id,
                "ticket"      => $ticket,
                "event"       => $ticket->event()->first(),
                "visit_date"  => $purchase->visitDate()->first(),
                "seat_number" => $purchase->seatNumber()->first(),
            ],
            200
        );
    }

    // thid function to get purchases by trx id
    public function purchases(Request $req)
    {
        $user     = Auth::user();
        $paysData = Payment::where('user_id', $user->id)->get();
        if (count($paysData) == 0) {
            return response()->json(["error" => "Payment data not found"], 404);
        }
        $payments = [];
        foreach ($paysData as $payData) {
            $payData->user = $user;
            $purchases     = $payData->purchases()->where('user_id', $user->id)->where('org_inv', false)->get();
            foreach ($purchases as $purchase) {
                $purchase->ticket      = $purchase->ticket()->first();
                $purchase->secret_info = $purchase->ticket->secretInfo()->first();
                if ($purchase->ticket->quantity == -1) {
                    $purchase->ticket->quantity = $purchase->ticket->limitDaily()->first()->limit_quantity;
                }
                $purchase->ticket->event                       = $purchase->ticket->event()->first();
                $purchase->ticket->event->available_days       = $purchase->ticket->event->availableDays()->get();
                $purchase->ticket->event->available_reschedule = $purchase->ticket->event->availableReschedule()->first();
                $purchase->ticket->event->org                  = $purchase->ticket->event->org()->first();
                $purchase->ticket->event->org->legality        = $purchase->ticket->event->org->credibilityData()->first();
                $purchase->visit_date                          = $purchase->visitDate()->first();
                $purchase->seat_number                         = $purchase->seatNumber()->first();
                $purchase->qr_str                              = $purchase->id . "*~^|-|^~*" . $user->id;
                $purchase->event_id                            = $purchase->ticket->event->id;
                $purchase->event_name                          = $purchase->ticket->event->name;
                $purchase->checkin                             = $purchase->checkin()->first();
            }
            $trx = null;
            if ($payData->pay_state == 'PENDING') {
                $trx = $this->loadTrxValidation($payData);
            } else {
                $trx = $payData;
            }
            $payments[] = [
                "payment"   => $trx,
                "purchases" => $purchases,
            ];
        }
        return response()->json(
            [
                "transactions" => $payments,
            ],
            200
        );
    }

    public function downloadTicket(Request $req)
    {
        $user = Auth::user();
        // $user = User::where('id', '9b08d7a9-fa50-4336-86dd-aeda11dd8271')->first();
        $pch = Purchase::where('id', $req->purchase_id)->where('user_id', $user->id)->with(['user', 'payment'])->first();
        if (! $pch) {
            return response()->json(["error" => "Data not found"], 404);
        }
        $start     = null;
        $end       = null;
        $time      = '';
        $visitDate = $pch->visitDate()->first();
        $ticket    = $pch->ticket()->first();
        $event     = $ticket->event()->first();
        if ($visitDate) {
            $visitDate = new DateTime($visitDate->visit_date, new DateTimeZone('Asia/Jakarta'));
            $start     = $visitDate;
            $end       = $visitDate;
        } else {
            $start = new DateTime($event->start_date . " " . $event->start_time, new DateTimeZone('Asia/Jakarta'));
            $end   = new DateTime($event->end_date . " " . $event->end_time, new DateTimeZone('Asia/Jakarta'));
            $time  = $start->format("H:i") . ' - ' . $end->format("H:i") . ' WIB';
        }
        $seatNumber = $pch->seatNumber()->first();

        $org           = $event->org()->first();
        $org->legality = $org->credibilityData()->first();
        $pdf           = SnappyPdf::loadView('pdfs.invoice-ticket-new', [
            "purchase" => $pch,
        ])->setPaper('a4')->setOrientation('portrait')->setOption('enable-local-file-access', true);
        return $pdf->download();
    }
}
