<?php

namespace App\Http\Controllers;

use App\Mail\ETicket;
use App\Mail\OrganizerTicketNotiffication;
use App\Models\FailedTrxNotification;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;

class ResendTrxNotification extends Controller
{
    public static function writeErrorLog(String $fn_path, String $type, Array $data, String $mainMail, String $secMail = null){
        /* ====================================================================
        NOTE : 
            Var. String $type is free defined by every function caller. But only
            for paid ticket transaction notification, use 'PAYMENT' as specific
            type.
        =======================================================================*/
        $strData = '';
        for ($i=0; $i < count($data); $i++) { 
            if($i+1 < count($data)){
                $strData = $strData . $data[$i] . '^~!@!~^'; 
            }else{
                $strData .= $data[$i];
            }
        }
        FailedTrxNotification::create([
            'mail_target' => $mainMail,
            'mail_sec_target' => $secMail,
            'fn_path' => $fn_path,
            'type' => $type,
            'str_data' => $strData,
        ]);
        return null;
    }

    public function get(){
        return response()->json(["data" => FailedTrxNotification::all()], 200);
    }

    public function delete(Request $req){
        $data = FailedTrxNotification::where('id',  $req->log_id);
        if(!$data->first()){
            return response()->json(["error" => "Log error not found"], 404);
        }
        $data->delete();
        return response()->json(["data" => FailedTrxNotification::all()], 202);
    }

    public function mainResend(Request $req){
        $validate = Validator::make($req->all(), [
            'log_id' => 'required', 
        ]);

        if($validate->fails()){
            return response()->json($validate->errors(), 403);
        }

        $errorLog = FailedTrxNotification::where('id', $req->log_id)->first();

        if(!$errorLog){
            return response()->json(["error" => "Log error not found"], 404);
        }

        $data = explode('^~!@!~^', $errorLog->str_data);

        try{
            if($errorLog->type === 'PAYMENT'){
                $payment = Payment::where('id', $data[0])->first();
                if(!$payment){
                    return response()->json(["error" => "Payment data not found"], 404);
                }
                Mail::to($errorLog->mail_sec_target)->send(new OrganizerTicketNotiffication($payment->id));
                Mail::to($errorLog->mail_target)->send(new ETicket($payment->id));
                
            }else{
                $reflectionClass = new ReflectionClass($errorLog->fn_path);
                Mail::to($errorLog->mail_target)->send($reflectionClass->newInstanceArgs($data));
                if($errorLog->mail_sec_target !== null && $errorLog->mail_sec_target !== ''){
                    Mail::to($errorLog->mail_sec_target)->send($reflectionClass->newInstanceArgs($data));
                }
            }
        } catch (\Throwable $th) {
            return response()->json(["error" => "Failed to connect mail server"], 400);
        }

        FailedTrxNotification::where('id', $req->log_id)->delete();
        return response()->json(["data" => FailedTrxNotification::all()], 202);
    }
}
