<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventSession;
use DateTimeZone;
use DateTime;


class RundownCtrl extends Controller
{
    private function comparator($obj1, $obj2)
    {
        $time1 = new DateTime($obj1['start_time'], new DateTimeZone('Asia/Jakarta'));
        $time2 = new DateTime($obj2['start_time'], new DateTimeZone('Asia/Jakarta'));
        return $time1->format('H:i:s') > $time2->format('H:i:s');
    }

    public function getRundowns(Request $req)
    {
        $rundowns = [];
        foreach (EventSession::where('event_id', $req->event->id)->get()->groupBy('start_date') as $key => $value) {
            $arrData = $value->toArray();
            usort($arrData, [$this, 'comparator']);
            $rundowns[$key] = $arrData;
        }
        return response()->json(["rundowns" => $rundowns], 200);
    }
}
