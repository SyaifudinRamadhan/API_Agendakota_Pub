<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrgInvitation;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class OrgInvCtrl extends Controller
{
    //
    private function coreSender(
        Request $req, // This param contains basical attribut in transaction as a asscociation array (all attribute described below in "Required attribute in request data"). This param not only having purpose for transaction, but also as a list order of tickets_data in invitation. That is by "ticket_ids" attribute
        array $name, // This param is an invitation data attribute about list order of names invitation destination
        array $mail_target, // This param is an invitation data attribute about list order of emails invitation destination
        array $wa_number, // This param is an invitation data attribute about list order of whyasapp numbers invitation destination (if using whatsapp)
        array $invoiceFile, // This param is an invitation data attribute about list order of attachmennts or notes invitation
        array $visitDates, // This param is an invitation data attribute about list order of ticket visit dates invitation if event of ticket is daily activties type
        array $seatNumbers // This param is an invitation data attribute about list order of ticket seat number invitation if ticket having seat numbering system
    ) {
        /*
        ============================================
        Required attribute in request data :
        1. ticket_ids
        2. pay_method
        3. name
        4. mail_target
        5. wa_number
        6. invoice (Optional. Is an image of invoice from transfer) => type file image jpg/png/pdf
        7. custom_prices (if having custom price)
        8. visit_dates (if having visit dates)
        9. seat_numbers (if having seat number option)

        NOTE :
        - invoiceFile param is an array invoiceFile that before has prepared to sync with main request data
        - In these param haven't must be sequentiallly and full filled. Buat sync with main data.
        - Every main data can ignore the invoice. So in this condition (index of this main data), must synced to invoiceFile array data.
        And array length of invoiceFile must be equal with array of main data.
        - For example if main data have 8 data in array so, in invoiceFile array must have 8 data to. But not must filled all.
        - EX : ['jhon', 'rick', 'erick'] => main data
        [fileData, null, fileData] => array of invoiceFile
        - Main data is name, mail_target, wa_number, and ticket_ids attribute from request parameter
        - And all array size of main data must be equal
        ============================================
         */

        if (count($name) === 0) {
            return response()->json(["error" => "Name attribute is required and used type is an array"], 403);
        }
        if (count($mail_target) === 0) {
            return response()->json(["error" => "Mail target attribute is required and used type is an array"], 403);
        }

        if (count($name) !== count($mail_target) ||
            count($req->ticket_ids) !== count($name) ||
            count($req->ticket_ids) !== count($mail_target) ||
            (count($wa_number) > 0 && count($req->ticket_ids) !== count($wa_number)) ||
            (
                count($invoiceFile) > 0 && (
                    count($invoiceFile) !== count($name) ||
                    count($invoiceFile) !== count($mail_target) ||
                    count($invoiceFile) !== count($req->ticket_ids)
                )
            )
        ) {
            return response()->json(["error" => "Name, mail_target, wa_number, and invoice file (if having this) must be have same array length",
            ], 403);
        }
        $pchCtrl = new PchCtrl();
        $res = $pchCtrl->create($req, true);
        if ($res->getStatusCode() !== 201) {
            return $res;
        }
        $invDatas = [];

        $oriPchs = $res->original["purchases"];
        $oriVisitDts = $res->original["visitDatesIns"];
        $oriSeatNums = $res->original["seatNumbersIns"];

        $pairedFromMatchPchIds = [];

        for ($i = 0; $i < count($name); $i++) {
            $matchPchId = [];
            for ($j = 0; $j < count($oriPchs); $j++) {
                if ($oriPchs[$j]["ticket_id"] == $req->ticket_ids[$i]) {
                    array_push($matchPchId, $oriPchs[$j]["id"]);
                }
            }

            if (isset($pairedFromMatchPchIds[strval($matchPchId[0])])) {
                $pairedFromMatchPchIds[strval($matchPchId[0])] += 1;
                $matchPchId = $matchPchId[$pairedFromMatchPchIds[strval($matchPchId[0])]];
            } else {
                if (count($matchPchId) > 1) {
                    $pairedFromMatchPchIds[strval($matchPchId[0])] = 0;
                }
                $matchPchId = $matchPchId[0];
            }

            $nameFile = null;
            if (count($invoiceFile) > 0 && $invoiceFile[$i] !== null) {
                $nameFile = BasicFunctional::randomStr(5) . '_' . time() . '.' . $invoiceFile[$i]->getClientOriginalExtension();
                $invoiceFile[$i]->storeAs('private/inv_attchs', $nameFile);
                $nameFile = '/manage/invitation-attachment/' . $nameFile;
            }

            array_push($invDatas, [
                'pch_id' => $matchPchId,
                'email' => $mail_target[$i],
                'wa_num' => count($wa_number) > 0 ? $wa_number[$i] : '-',
                'name' => $name[$i],
                'trx_img' => $nameFile,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        OrgInvitation::insert($invDatas);
        $clearWaArray = array_filter($wa_number, function ($number) {return $number !== '-';});
        if (count($clearWaArray) > 0) {
            Organization::where('id', $req->org->id)->update([
                'create_inv_quota' => intval($req->org->create_inv_quota) - count($clearWaArray),
            ]);
        }
        return response()->json(["message" => "Invitation has successfully created"], 201);
    }

    public function singleCreateInv(Request $req)
    {
        $parameterValidator = [
            "ticket_id" => "required|string",
            'name' => 'required|string',
            'mail_target' => 'required|string',
            'invoice' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
        ];
        if (isset($req->with_wa) && $req->with_wa == true) {
            if ($req->org->allow_create_inv == false || $req->org->create_inv_quota === 0) {
                return response()->json(["message" => "This feature isn't activate for your organization"], 403);
            }
            $parameterValidator["wa_number"] = 'required|numeric';
        }
        $validator = Validator::make($req->all(), $parameterValidator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $req->ticket_ids = [$req->ticket_id];

        $visitDates = [];
        $seatNumbers = [];

        if (isset($req->visit_date) && $req->visit_date != null && $req->visit_date != '' && $req->visit_date != ' ') {
            array_push($visitDates, $req->visit_date);
            $req->visit_dates = json_encode([
                $req->ticket_id => [$req->visit_date],
            ]);
        }

        if (isset($req->seat_number) && $req->seat_number != null && $req->seat_number != '' && $req->seat_number != ' ') {
            array_push($seatNumbers, $req->seat_number);
            $req->seat_numbers = json_encode([
                $req->ticket_id => $req->seat_number,
            ]);
        }

        return $this->coreSender(
            $req,
            [$req->name],
            [$req->mail_target],
            isset($req->with_wa) && $req->with_wa == true ? [$req->wa_number] : [],
            $req->hasFile('invoice') ? [$req->file('invoice')] : [],
            $visitDates,
            $seatNumbers
        );
    }

    public function bulkCreateInv(Request $req)
    {
        /* ==========================================================
        NOTE:
        - Input of this function that is an spreadsheet data (xls or xlsx). Which contain all selected ticket name,
        mail target, name of target, whatsapp number, and invoice image name if have it.
        - If xls file contain invoice image name, so this function must have all image data that was refered on xls file
        ========================================================== */
        $validator = Validator::make($req->all(), [
            "spread_file" => "required|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel, text/xls, text/xlsx, application/vnd.msexcel",
            "invoice_datas" => "nullable|array",
            "invoice_datas.*" => "nullable|file|mimes:jpg,png,pdf|max:2048",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }

        $arrayData = null;
        if ($req->file("spread_file")->getClientOriginalExtension() === "xlsx") {
            $reader = new Xlsx();
            $spreadsheet = $reader->load('/tmp/' . $req->file('spread_file')->getFilename())->getSheet(0);
            $arrayData = $spreadsheet->toArray();
        } else {
            $reader = new Xls();
            $spreadsheet = $reader->load('/tmp/' . $req->file('spread_file')->getFilename())->getSheet(0);
            $arrayData = $spreadsheet->toArray();
        }
        // *Prepare request attributes for connecting to pchCtrl->create function
        $req->ticket_ids = [];

        // *Prepare data for parameter to connecting pchCtrl->crate function
        $names = [];
        $mailTargets = [];
        $waNumbers = [];
        $invoiceImages = [];
        $visitDates = [];
        $seatNumbers = [];

        $invoiceFiles = $req->hasFile('invoice_datas') && gettype($req->file('invoice_datas')) === "object" ? [$req->file('invoice_datas')] : ($req->hasFile('invoice_datas') ? $req->file('invoice_datas') : null);

        // *Get All ticket from event id
        $tickets = Ticket::where('event_id', $req->event->id)->where('deleted', 0)->get()->toArray();

        // *Insert data to request attributes
        unset($arrayData[0]);
        foreach ($arrayData as $index => $data) {
            $resSearch = array_search($data[0], array_column($tickets, 'name'));
            if (gettype($resSearch) === "integer" && $resSearch >= 0) {
                $arrayData[$index][0] = $tickets[$resSearch]["id"];
                $data[0] = $tickets[$resSearch]["id"];
            } else {
                $arrayData[$index][0] = null;
                $data[0] = null;
            }
            if (($data[0] != null && $data[0] != "null" && $data[0] != "-" && $data[0] != "" && $data[0] != " ") &&
                ($data[3] != null && $data[3] != "null" && $data[3] != "-" && $data[3] != "" && $data[3] != " ") &&
                ($data[4] != null && $data[4] != "null" && $data[4] != "-" && $data[4] != "" && $data[4] != " ")
                // (isset($req->with_wa) && $req->with_wa == true ? ($data[5] != null && $data[5] != "null" && $data[5] != "-" && $data[5] != "" && $data[5] != " ") : true)
            ) {
                array_push($req->ticket_ids, $data[0]);
                array_push($names, $data[3]);
                array_push($mailTargets, $data[4]);
                if (isset($req->with_wa) && $req->with_wa == true && ($data[5] != null && $data[5] != "null" && $data[5] != "-" && $data[5] != "" && $data[5] != " ")) {
                    array_push($waNumbers, $data[5]);
                } else if (isset($req->with_wa) && $req->with_wa == true) {
                    array_push($waNumbers, '-');
                }
                if ($invoiceFiles !== null) {
                    $resFilter = array_filter($invoiceFiles, function ($file) use ($data) {
                        return $file->getClientOriginalName() === $data[6] ? $file : null;
                    });
                    array_push($invoiceImages, count($resFilter) === 0 ? null : array_pop($resFilter));
                }
                // *Prepare request attributes for connecting to pchCtrl->create function
                if ($data[1] != null && $data[1] != "null" && $data[1] != "-" && $data[1] != "" && $data[1] != " " && !isset($req->visit_dates)) {
                    $req->visit_dates = [];
                    $req->visit_dates[$data[0]] = [$data[1]];
                } else if ($data[1] != null && $data[1] != "null" && $data[1] != "-" && $data[1] != "" && $data[1] != " ") {
                    isset($req->visit_dates[$data[0]]) ? array_push($req->visit_dates[$data[0]], $data[1]) : $req->visit_dates[$data[0]] = [$data[1]];
                }
                // *Prepare request attributes for connecting to pchCtrl->create function
                if ($data[2] != null && $data[2] != "null" && $data[2] != "-" && $data[2] != "" && $data[2] != " " && !isset($req->seat_numbers)) {
                    $req->seat_numbers = [];
                    $req->seat_numbers[$data[0]] = [$data[2]];
                } else if ($data[2] != null && $data[2] != "null" && $data[2] != "-" && $data[2] != "" && $data[2] != " ") {
                    isset($req->seat_numbers[$data[0]]) ? array_push($req->seat_numbers[$data[0]], $data[2]) : $req->seat_numbers[$data[0]] = [$data[2]];
                }
                // *Insert data from visit date and seat number column to array visitDates and seatNumbers for re-mapping invitation after get purchase data
                array_push($visitDates, $data[1] != null && $data[1] != "null" && $data[1] != "-" && $data[1] != "" && $data[1] != " " ? $data[1] : null);
                array_push($seatNumbers, $data[2] != null && $data[2] != "null" && $data[2] != "-" && $data[2] != "" && $data[2] != " " ? $data[2] : null);
            }
        }
        if (isset($req->with_wa) && $req->with_wa == true && ($req->org->allow_create_inv == false || $req->org->create_inv_quota < count($waNumbers))) {
            return response()->json(["error" => "Your whatsapp invitation quota is low"], 403);
        }
        if(count($req->ticket_ids) === 0){
            return response()->json(["error" => "Ticket data is blank or not found. Please input valid ticket name from your event"], 404);
        }
        // dd($req->ticket_ids, $req->visit_dates, $req->seat_numbers ,$names, $mailTargets, $waNumbers, $invoiceImages);
        return $this->coreSender($req, $names, $mailTargets, $waNumbers, $invoiceImages, $visitDates, $seatNumbers);
    }

    public function bulkCreateInvV2(Request $req)
    {
        /*
        =============================================================
        NOTE : Mean on bulk create invitation v2 is an fuction to create invitations by using JSON / Form Request instead using XLS file. But for
        the form data / request data is equal with contain in XLS upload function above.
        =============================================================
         */
        $parameterValidator = [
            "ticket_ids" => "required|array",
            'names' => 'required|array',
            'mail_targets' => 'required|array',
            'invoices' => 'nullable|array',
            'invoices.*' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
            'file_orders' => 'nullable|array',
            'file_orders.*' => 'numeric',
            'visit_dates_ins' => 'nullable|array',
            'seat_numbers_ins' => 'nullable|array',
        ];
        if (isset($req->with_wa) && $req->with_wa == true) {
            $parameterValidator["wa_numbers.*"] = 'required|numeric';
        }
        $validator = Validator::make($req->all(), $parameterValidator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        if (isset($req->with_wa) && $req->with_wa == true && ($req->org->allow_create_inv == false || $req->org->create_inv_quota < count($req->ticket_ids))) {
            return response()->json(["message" => "This feature isn't activate for your organization"], 403);
        }
        if (!(count($req->ticket_ids) == count($req->names) &&
            count($req->names) == count($req->mail_targets) &&
            (isset($req->with_wa) && $req->with_wa == true ? count($req->mail_targets) == count($req->wa_numbers) : true))) {
            return response()->json(["error" => "All data except invoices and whatsapp number (if using whatsapp), must be have same length of array data"], 403);
        }

        $visitDates = [];
        $seatNumbers = [];
        if ((isset($req->visit_dates_ins) && count($req->visit_dates_ins) == count($req->ticket_ids)) || (isset($req->seat_numbers_ins) && count($req->seat_numbers_ins) == count($req->ticket_ids))) {
            for ($i = 0; $i < count($req->ticket_ids); $i++) {
                // *Prepare request attributes for connecting to pchCtrl->create function
                if (isset($req->visit_dates_ins) && count($req->visit_dates_ins) == count($req->ticket_ids) && $req->visit_dates_ins[$i] != null && $req->visit_dates_ins[$i] != "null" && $req->visit_dates_ins[$i] != "-" && $req->visit_dates_ins[$i] != "" && $req->visit_dates_ins[$i] != " " && !isset($req->visit_dates)) {
                    $req->visit_dates = [];
                    $req->visit_dates[$req->ticket_ids[$i]] = [$req->visit_dates_ins[$i]];
                } else if (isset($req->visit_dates_ins) && count($req->visit_dates_ins) == count($req->ticket_ids) && $req->visit_dates_ins[$i] != null && $req->visit_dates_ins[$i] != "null" && $req->visit_dates_ins[$i] != "-" && $req->visit_dates_ins[$i] != "" && $req->visit_dates_ins[$i] != " ") {
                    isset($req->visit_dates[$req->ticket_ids[$i]]) ? array_push($req->visit_dates[$req->ticket_ids[$i]], $req->visit_dates_ins[$i]) : $req->visit_dates[$req->ticket_ids[$i]] = [$req->visit_dates_ins[$i]];
                }
                // *Prepare request attributes for connecting to pchCtrl->create function
                if (isset($req->seat_numbers_ins) && count($req->seat_numbers_ins) == count($req->ticket_ids) && $req->seat_numbers_ins[$i] != null && $req->seat_numbers_ins[$i] != "null" && $req->seat_numbers_ins[$i] != "-" && $req->seat_numbers_ins[$i] != "" && $req->seat_numbers_ins[$i] != " " && !isset($req->seat_numbers)) {
                    $req->seat_numbers = [];
                    $req->seat_numbers[$req->ticket_ids[$i]] = [$req->seat_numbers_ins[$i]];
                } else if (isset($req->seat_numbers_ins) && count($req->seat_numbers_ins) == count($req->ticket_ids) && $req->seat_numbers_ins[$i] != null && $req->seat_numbers_ins[$i] != "null" && $req->seat_numbers_ins[$i] != "-" && $req->seat_numbers_ins[$i] != "" && $req->seat_numbers_ins[$i] != " ") {
                    isset($req->seat_numbers[$req->ticket_ids[$i]]) ? array_push($req->seat_numbers[$req->ticket_ids[$i]], $req->seat_numbers_ins[$i]) : $req->seat_numbers[$req->ticket_ids[$i]] = [$req->seat_numbers_ins[$i]];
                }
                // *Insert data from visit date and seat number column to array visitDates and seatNumbers for re-mapping invitation after get purchase data
                array_push($visitDates, isset($req->visit_dates_ins) && count($req->visit_dates_ins) == count($req->ticket_ids) && $req->visit_dates_ins[$i] != null && $req->visit_dates_ins[$i] != "null" && $req->visit_dates_ins[$i] != "-" && $req->visit_dates_ins[$i] != "" && $req->visit_dates_ins[$i] != " " ? $req->visit_dates_ins[$i] : null);
                array_push($seatNumbers, isset($req->seat_numbers_ins) && count($req->seat_numbers_ins) == count($req->ticket_ids) && $req->seat_numbers_ins[$i] != null && $req->seat_numbers_ins[$i] != "null" && $req->seat_numbers_ins[$i] != "-" && $req->seat_numbers_ins[$i] != "" && $req->seat_numbers_ins[$i] != " " ? $req->seat_numbers_ins[$i] : null);
            }
        }
        $arrFiles = [];
        if ($req->hasFile('invoices') && isset($req->file_orders) && count($req->file('invoices')) == count($req->file_orders)) {
            $arrFiles = array_fill(0, count($req->names), null);
            for ($i = 0; $i < count($req->names); $i++) {
                $search = array_search($i, $req->file_orders);
                if ($search >= 0 && $search !== false) {
                    $arrFiles[$i] = $req->file('invoices')[$search];
                }
            }
        }
        return $this->coreSender(
            $req,
            $req->names,
            $req->mail_targets,
            isset($req->with_wa) && $req->with_wa == true ? $req->wa_numbers : [],
            $arrFiles,
            $visitDates,
            $seatNumbers
        );
    }

    public function cancelInv(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "inv_ids" => "required|array",
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $invs = OrgInvitation::whereIn('id', $req->inv_ids)->get();
        if (count($invs) == 0) {
            return response()->json(["error" => "Invitation not found"], 404);
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => env('MINI_SERVICE_URL_1') . "api/v1/cancel-inv",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_POSTFIELDS => json_encode([
                "inv_ids" => $req->inv_ids,
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ]);
        $res = curl_exec($curl);
        if (!$res) {
            return response()->json(["error" => "Failed reach express server"], 500);
        }
        curl_close($curl);
        $res = json_decode($res);
        if ($res->status == 202) {
            for ($i = 0; $i < count($invs); $i++) {
                $oriName = explode('/', $invs[$i]->trx_img);
                Storage::delete('private/inv_attchs/' . $oriName[count($oriName) - 1]);
            }
        }
        return response()->json($res, $res->status);
    }

    public function getAll(Request $req)
    {
        $tickets = Ticket::where('event_id', $req->event->id)->where('deleted', 0)->get();
        if (count($tickets) === 0) {
            return response()->json(["error" => "Invitations not found, Have no ticket data"], 404);
        }
        $invitations = [];
        foreach ($tickets as $key => $ticket) {
            array_push($invitations, $ticket->purchases()->where('org_inv', true)->orderBy('created_at', 'DESC')->with(['orgInv', 'visitDate', 'seatNumber', 'ticket'])->get());
        }
        return response()->json([
            "invitations" => $invitations,
            "tickets" => $tickets,
        ], 200);
    }
}
