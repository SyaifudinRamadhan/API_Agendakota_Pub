<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StageCtrl extends Controller
{
    // THIS CONTROLLER IS AVAILABLE FOR ONLINE AND HYBRID EVENT ONLY.
    // FISRT STEP, THIS CTRL IS POSPONED, BECAUSE SYSTEM WILL BE FOCUSED TO OFFLINE EVENT
    private function checkLink($link, $type)
    {
        $linkFor = "";
        if (strpos($link, "zoom.us") == true && $type == '003') {
            $linkFor = "zoom";
        } else if ((strpos($link, "youtube.com") == true || strpos($link, 'youtu.be') == true) && $type == '004') {
            $linkFor = "youtube";
        } else {
            return -1;
        }
        if ($linkFor == "zoom") {
            $e = explode("?", $link);
            if (count($e) == 0) {
                return -1;
            } else {
                $p = explode("/", $e[0]);
                $pass = explode("pwd=", $e[1]);
            }
            if (count($p) == 0) {
                return -1;
            }
            if (count($pass) == 0 || count($pass) == 1) {
                return -1;
            }
            return $link;
        } else if ($linkFor == "youtube") {
            $idVideo = "";
            if (!preg_match('/^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=|\?v=)([^#\&\?]*).*/', $link)) {
                return -1;
            }
            $urlInput = explode('watch?v=', $link);
            if (count($urlInput) <= 1) {
                $urlInput = explode('youtu.be/', $link);
                if (count($urlInput) <= 1) {
                    $urlInput = explode('/embed/', $link);
                }
                $idVideo = $urlInput[1];
                $idVideo = explode('&', $idVideo);
            } else {
                $idVideo = $urlInput[1];
                $idVideo = explode('&', $idVideo);
            }
            return ("https://www.youtube.com/embed/" . $idVideo[0] . '?modestbranding=1&showinfo=0');
        }
    }
}
