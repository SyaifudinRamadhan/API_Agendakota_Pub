<?php

namespace App\Http\Controllers;

use App\Models\CustomUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomUrlCtrl extends Controller
{
    public function createUpdate (Request $req) {
        $validator = Validator::make($req->all(), [
            "str_custom" => "required|string|max:254|unique:custom_urls"
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 403);
        }

        if(preg_match_all("/[~,`,!,@,#,$,%,^,&,*,(,),+,=,?,\',\",\.,\,<,>,\],\[,\/,\|,},{,:,;,\\\]/", $req->str_custom)){
            return response()->json(["error" => "Only receive letter, number, (-), and (_) character"], 403);
        }

        $event = $req->event;
        $customUrl = CustomUrl::where('event_id', $event->id);
        if($customUrl->first()){
            $customUrl->update([
                "str_custom" => $req->str_custom
            ]);
            $customUrl = $customUrl->first();
        }else{
            $customUrl = CustomUrl::create([
                "event_id" => $event->id,
                "str_custom" => $req->str_custom
            ]);
        }
        return response()->json(["data" => $customUrl], 202);
    }

    public function get (Request $req) {
        $event = $req->event;
        $customUrl = CustomUrl::where('event_id', $event->id)->first();
        return response()->json(["data" => $customUrl], $customUrl ? 200 : 404);
    }

    public function getEvent (Request $req, $strKey) {
        $customUrl = CustomUrl::where('str_custom', $strKey)->first();
        if(!$customUrl){
            return response()->json(["error" => "Event data not found"], 404);
        }
        return response()->json(["data" => $customUrl], 200);
    }
}
