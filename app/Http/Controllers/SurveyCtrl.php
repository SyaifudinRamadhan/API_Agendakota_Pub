<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldSurvey;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SurveyCtrl extends Controller
{

    /*
    Example string survey field is 

        name_field~!!!~type_field~!!!~required_state

    1. Must have 3 parameter and concat it with string '~!!!~'
    2. If string have less or more than 3 parameter, create system is still accept it. But, fillSurveyUser function (for write answer data) will auto reject it
    3. Answers data only the answear in array type data
    EX :
    -> Question: name~!!!~text~!!!~required_state|city~!!!~text~!!!~required_state
    -> Answer: [Jhon, Los Angeles]
    */

    public function fillSurveyUser(Request $req)
    {
        $user = Auth::user();
        $validator = Validator::make($req->all(), [
            "event_id" => "required|string",
            "survey_ans" => "required|array",
            "files_data" => "array"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }
        // if (CustomFieldSurvey::where('user_id', $user->id)->where('event_id', $req->event_id)->first()) {
        //     return response()->json(["message" => "Thank you for filling out the survey"], 201);
        // }
        $event = Event::where('id', $req->event_id)->where('is_publish', 2)->where('deleted', 0)->first();
        if (!$event) {
            return response()->json(["error" => "Event data not found"], 404);
        }
        if ($event->custom_fields == null || $event->custom_fields == '') {
            return response()->json(["error" => "Event haven't survey fields"], 404);
        }
        $customFields = explode('|', $event->custom_fields);
        if (count($req->survey_ans) != count($customFields)) {
            return response()->json(["error" => "Total of all answear not match with total of survey quentions"], 403);
        }
        $ansSurvey = null;
        foreach ($req->survey_ans as $index => $ans) {
            $tmpStr = null;
            $fieldData = explode('~!!!~', $customFields[$index]);
            if (count($fieldData) !== 3) {
                return response()->json(["error" => "Illegal string question format"], 403);
            }

            if (($fieldData[2] == "required") &&
                $fieldData[1] === "file"
            ) {
                // check required data and if type file
                if ($ans === -1 || $ans == "-1" || !$req->file('files_data')) {
                    return response()->json(["error" => "This field required file data"], 403);
                } else if (count($req->file("files_data")) <= 0) {
                    return response()->json(["error" => "This field required file data"], 403);
                } else if (count($req->file("files_data")) - 1 < abs((int)($ans))) {
                    return response()->json(["error" => "This field required file data"], 403);
                } else if (($req->file("files_data")[abs((int)$ans)]->getSize() / 1024) >= 1024) {
                    return response()->json(["error" => "File size is to large. Max size is 1 Mb"], 403);
                }
                // $filename = pathinfo($req->file("files_data")[abs((int)$ans)]->getClientOriginalName(), PATHINFO_FILENAME);
                $filename = BasicFunctional::randomStr(5) . "_" . time() . "." . $req->file("files_data")[abs((int)$ans)]->getClientOriginalExtension();
                $req->file("files_data")[abs((int)$ans)]->storeAs('public/survey_ans_images', $filename);
                $tmpStr = '/storage/survey_ans_images/' . $filename;
            } else if ($fieldData[1] === "file") {
                // check is type file
                if ($ans === -1 || $ans == "-1" || !$req->file('files_data')) {
                    $tmpStr = "-";
                } else if (count($req->file("files_data")) <= 0) {
                    $tmpStr = "-";
                } else if (count($req->file("files_data")) - 1 < abs((int)($ans))) {
                    $tmpStr = "-";
                } else if (($req->file("files_data")[abs((int)$ans)]->getSize() / 1024) >= 1024) {
                    return response()->json(["error" => "File size is to large. Max size is 1 Mb"], 403);
                } else {
                    // $filename = pathinfo($req->file("files_data")[abs((int)$ans)]->getClientOriginalName(), PATHINFO_FILENAME);
                    $filename = BasicFunctional::randomStr(5) . "_" . time() . "." . $req->file("files_data")[abs((int)$ans)]->getClientOriginalExtension();
                    $req->file("files_data")[abs((int)$ans)]->storeAs('public/survey_ans_images', $filename);
                    $tmpStr = '/storage/survey_ans_images/' . $filename;
                }
            } else if ($fieldData[2] == "required") {
                // check is required
                if ($ans == "" || $ans == " " || $ans == null || $ans == "null") {
                    return response()->json(["error" => "this field is required"], 403);
                }
                $tmpStr = $ans;
            } else {
                // other
                if ($ans == "" || $ans == " " || $ans == null || $ans == "null") {
                    $tmpStr = "-";
                } else {
                    $tmpStr = $ans;
                }
            }

            if ($ansSurvey == null) $ansSurvey = $tmpStr;
            else $ansSurvey = $ansSurvey . '|' . $tmpStr;
        }
        CustomFieldSurvey::create([
            "user_id" => $user->id,
            "event_id" => $event->id,
            "question_str" => $event->custom_fields,
            "survey_datas" => $ansSurvey
        ]);
        return response()->json(["message" => "Thank you for filling out the survey"], 201);
    }

    public function getSurvey(Request $req)
    {
        $surveyDatas = [];
        foreach ($req->event->surveys()->get() as $data) {
            $data->user = $data->user()->first();
            $data->survey_datas = explode('|', $data->survey_datas);
            $data->question_str = explode('|', $data->question_str);
            $surveyDatas[] = $data;
        }
        return response()->json(["data" => $surveyDatas], 200);
    }
}
