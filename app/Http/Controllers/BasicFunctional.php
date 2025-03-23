<?php 
namespace App\Http\Controllers;

class BasicFunctional extends Controller {
  public static function randomStr($length = 4){
    $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $randStr = "";
    for ($i=0; $i < $length; $i++) { 
      $randStr .= $chars[rand(0, strlen($chars)-1)];
    }
    return $randStr;
  }
}

?>