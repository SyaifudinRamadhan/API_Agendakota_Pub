<?php

namespace App\Http\Middleware;

use App\Mail\TwoFactorNoitication;
use App\Models\TwoFactorAuth as ModelsTwoFactorAuth;
use App\Models\User;
use Closure;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\Cast\Array_;

class TwoFactorAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

     /*
        Stucture of user session both for database version

        $_SESSION["{userId}_2FASession"] = [
            "token" => {user token auth},
            "ip_address" => {user ip address from request},
            "otp_code" => {OTP for verify 2FA},
            "state" => {Verification state (pending/verified)},
            "exp_to_verify" => {timestamp for expired OTP to verify}
        ]
     */

    private function renewSession(String $key, Array $newData) {
        ModelsTwoFactorAuth::where('user_id', $key)->update($newData);
        Log::info("update-session-data", (Array) ModelsTwoFactorAuth::where('user_id', $key)->first());
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header("Authorization");
        $user = Auth::user();
        if($user->two_factor == 0){
            return $next($request);
        }
        $userSession = ModelsTwoFactorAuth::where('user_id', $user->id)->first();

        $userIp = explode(".",$request->ip());
        $userIp = count($userIp) === 4 ? $userIp[0].".".$userIp[1].".".$userIp[2] : "";

        if(!$userSession){
            Log::info('Re-generate OTP on ELSE GET-Check '.$user->id. ' '. $request->url());
            return response()->json(["message" => "Kode autentikasi akan dikirim otomatis"], 405);
        }

        $exp = new DateTime($userSession->exp_to_verify, new DateTimeZone('Asia/Jakarta'));
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));

        if($userSession && $userSession->ip_address === $userIp && $userSession->state == 1 && $userSession->token === $token){
            return $next($request);
        }else if($userSession && $userSession->ip_address === $userIp && $userSession->state == 1){
            $this->renewSession($user->id, [
                "token" => $token
            ]);
            return $next($request);
        }else if($userSession && $userSession->ip_address !== $userIp && $request->query('otp_2fa')){
            if($now <= $exp && $userSession->otp_code === $request->otp_2fa && $userSession->state == 0 ){
                $this->renewSession($user->id, [
                    "token" => $token,
                    "ip_address" => $userIp,
                    "state" => true
                ]);
                return $next($request);
            }else if($now > $exp){
                Log::info('Re-generate OTP on IF Condition GET-Check '.$user->id.' '.$request->url());
                return response()->json(["message" => "Kode autentikasi akan dikirim otomatis"], 405);
            }else{
                return response()->json(["message" => "Kode autentikasi salah. Coba periksa kembali"], 405);
            }
        }else if($userSession && $now <= $exp){
            return response()->json(["message" => "Mohon maaf. Kode OTP bisa didapatkan kembali setelah ".$exp->diff($now)->i." menit ".$exp->diff($now)->s." detik dari permintaan terakhir."], 405);
        }else{
            Log::info('Re-generate OTP on ELSE GET-Check '.$user->id. ' '. $request->url());
            return response()->json(["message" => "Kode autentikasi akan dikirim otomatis"], 405);
        }
    }
}
