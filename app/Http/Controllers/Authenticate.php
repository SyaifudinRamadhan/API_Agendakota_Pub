<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Otp;
use App\Mail\Verification;
use App\Mail\ResetPassword;
use App\Mail\VerificationAutoLogin;
use App\Mail\VerificationBank;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class Authenticate extends Controller
{
    // ***************** NOTE *****************
    // This auth, using sanctum middleware. Because that, to get user data in all process after middleware isLogin, can be accessed by keyword $req->user or Auth::user().
    // ****************************************

    //Register account
    public function register(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                'f_name' => 'required|string',
                'l_name' => 'required|string',
                'name' => 'required|string',
                'email' => 'required|string|unique:users',
                'password' => 'required|string|min:8',
                'photo' => 'image|max:2048',
                'phone' => 'required|string',
                'linkedin' => 'required|string',
                'instagram' => 'required|string',
                'twitter' => 'required|string',
                'whatsapp' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        if (!filter_var($req->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(["error" => "Email is not valid"], 403);
        }
        // create uniq filename
        $namePhoto = '/storage/avatars/default.png';
        if ($req->hasFile('photo')) {
            // $originName = pathinfo($req->file('photo')->getClientOriginalName(), PATHINFO_FILENAME);
            $namePhoto = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('photo')->getClientOriginalExtension();

            // save image data
            $req->file('photo')->storeAs('public/avatars', $namePhoto);
            $namePhoto = '/storage/avatars/' . $namePhoto;
        }

        $user = User::create(
            [
                'f_name' => $req->f_name,
                'l_name' => $req->l_name,
                'name' => $req->name,
                'email' => $req->email,
                'password' => Hash::make($req->password),
                'g_id' => '-',
                'photo' => $namePhoto,
                'is_active' => '0',
                'phone' => $req->phone,
                'linkedin' => $req->linkedin,
                'instagram' => $req->instagram,
                'twitter' => $req->twitter,
                'whatsapp' => $req->whatsapp,
                "deleted" => 0
            ]
        );
        $mail_status = true;
        if (!$req->for_admin || $req->for_admin == false) {
            $tokenVerify = JWT::encode(['sub' => $user->id], env('JWT_SECRET'), env('JWT_ALG'));

            // Mail handler to verify
            try {
                Mail::to($req->email)->send(new Verification($user->name, $tokenVerify));
            } catch (\Throwable $th) {
                ResendTrxNotification::writeErrorLog('App\Mail\Verification', 'verification', [$user->name, $tokenVerify], $req->email);
                $mail_status = false;
            }
        }
        $token = null;
        if ($req->is_mobile ==  true) {
            $user->tokens()->where('name', 'auth_token_mobile')->delete();
            $token = $user->createToken('auth_token_mobile')->plainTextToken;
        } else {
            $user->tokens()->where('name', 'auth_token_web')->delete();
            $token = $user->createToken('auth_token_web')->plainTextToken;
        }
        return response()->json(
            [
                'data' => $user,
                'admin' => $user->admin()->first(),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'mail_status' => $mail_status
            ],
            201
        );
    }

    //Login account v. standart
    public function login(Request $req)
    {
        if (!Auth::attempt($req->only('email', 'password'))) {
            return response()->json(['error' => 'email or password not found'], 404);
        }

        $user = User::where('email', $req->email)->first();

        if (!$user || $user->is_active != '1') {
            return response()->json(['error' => 'Unauthorized, your account is not active. Please confirm your account first'], 401);
        }
        if ($user->deleted === 1) {
            return response()->json(["error" => "This account has removed"], 404);
        }
        $token = null;
        if ($req->is_mobile ==  true) {
            $user->tokens()->where('name', 'auth_token_mobile')->delete();
            $token = $user->createToken('auth_token_mobile')->plainTextToken;
        } else {
            $user->tokens()->where('name', 'auth_token_web')->delete();
            $token = $user->createToken('auth_token_web')->plainTextToken;
        }
        return response()->json(
            [
                'data' => $user,
                'admin' => $user->admin()->first(),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ],
            200
        );
    }

    public function autoLoginForBasicAuth (Request $req) {
        $user = null;
        // $password = Str::password(15, true, true, true, false);
        if($req->credential){
            $validator = Validator::make(
                $req->all(),
                [
                    'email' => 'required|string',
                    'credential' => 'required|string'
                ]
            );
            // This credential is remaked from frontend by google one tap reponse. Not from google response credential
            if ($validator->fails()) {
                return response()->json($validator->errors(), 403);
            }
            $user = User::where('email', $req->email)->first();
            $payloadAcc = '';
                try {
                    $payloadAcc = JWT::decode($req->credential, new Key(env('JWT_SECRET'), env('JWT_ALG')));
                } catch (\Throwable $th) {
                    return response()->json(
                        [
                            'error' => 'Invalid signature credential',
                        ],
                        403
                    );
                }
                Log::info(json_encode($payloadAcc));
            if ($user) {
                if ($user->g_id != $payloadAcc->sub || $user->is_active != '1' || $user->deleted === 1) {
                    if($user->g_id === "-"){
                        User::where('id', $user->id)->update([
                            'g_id' => $payloadAcc->sub,
                            'is_active' => '1',
                            'deleted' => 0
                        ]);
                    }else{
                        return response()->json(['error' => 'Unauthorized, your account is not active. Or not registered'], 401);
                    }
                }
            } else {
                if (!filter_var($req->email, FILTER_VALIDATE_EMAIL)) {
                    return response()->json(["error" => "Email is not valid"], 403);
                }
                $user = User::create(
                    [
                        'f_name' => isset($payloadAcc->given_name) ? $payloadAcc->given_name : '-',
                        'l_name' => isset($payloadAcc->family_name) ? $payloadAcc->family_name : '-',
                        'name' => isset($payloadAcc->name) ? $payloadAcc->name : '-',
                        'email' => $payloadAcc->email,
                        'password' => Hash::make(env('SECRET_PASS_BACKDOOR_GOOGLE_LOGIN')),
                        'g_id' => $payloadAcc->sub,
                        'photo' => '/storage/avatars/default.png',
                        'is_active' => '1',
                        'phone' => '-',
                        'linkedin' => '-',
                        'instagram' => '-',
                        'twitter' => '-',
                        'whatsapp' => '-',
                        "deleted" => 0
                    ]
                );
            }
        }else{
            $validator =  Validator::make($req->all(), [
                "email" => 'required|string|email',
                "name" => 'required|string',
                "password" => 'required|string',
                "whatsapp" => "required|numeric|min:10"
            ]);
            if($validator->fails()){
                return response()->json($validator->errors(), 403);
            }
            // check register status
            $user = User::where('email', $req->email)->first();
            if($user){
                User::where('email', $req->email)->update($req->whatsapp ? [
                    'whatsapp' => $req->whatsapp ? $req->whatsapp : '-',
                    'deleted' => 0,
                    'is_active' => '1'
                ] : [
                    'deleted' => 0,
                    'is_active' => '1'
                ]);
            }else{
                // create uniq filename
                $namePhoto = '/storage/avatars/default.png';
                $arrName = explode(' ', $req->name);
                $user = User::create(
                    [
                        'f_name' => $arrName[0],
                        'l_name' => $arrName[count($arrName)-1] === "" ? "-" : $arrName[count($arrName)-1],
                        'name' => $req->name,
                        'email' => $req->email,
                        'password' => Hash::make($req->password),
                        'g_id' => '-',
                        'photo' => $namePhoto,
                        'is_active' => '1',
                        'phone' => '-',
                        'linkedin' => '-',
                        'instagram' => '-',
                        'twitter' => '-',
                        'whatsapp' => $req->whatsapp ? $req->whatsapp : '-',
                        "deleted" => 0
                    ]
                );
        
                try {
                    Mail::to($req->email)->send(new VerificationAutoLogin($user->name, $user->email, $req->password));
                } catch (\Throwable $th) {
                    User::where('id', $user->id)->delete();
                    Log::info("Error With Mail Server : Failed send mail transaction. Transaction reset");
                    return response()->json(["error" => "Mail server error. Please try again later"], 500);
                }
            }
        }

        Auth::login($user);
        $pchCtrl = new PchCtrl();
        $surveyCtrl = new SurveyCtrl();
        $response = $pchCtrl->create($req);
        if(is_array($req->survey_ans) && count($req->survey_ans) > 0){
            $surveyCtrl->fillSurveyUser($req);
        }
        Auth::logout();
        return $response;
    }

    //login account v. g_id
    private function registerWithGoogle($credential, $isMobile = false)
    {
        // This credential is remaked from frontend by google one tap reponse. Not from google response credential
        $payloadAcc = '';
        try {
            $payloadAcc = JWT::decode($credential, new Key(env('JWT_SECRET'), env('JWT_ALG')));
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'error' => 'Invalid signature credential',
                ],
                403
            );
        }
        Log::info(json_encode($payloadAcc));
        $user = User::create(
            [
                'f_name' => isset($payloadAcc->given_name) ? $payloadAcc->given_name : '-',
                'l_name' => isset($payloadAcc->family_name) ? $payloadAcc->family_name : '-',
                'name' => isset($payloadAcc->name) ? $payloadAcc->name : '-',
                'email' => $payloadAcc->email,
                'password' => Hash::make(env('SECRET_PASS_BACKDOOR_GOOGLE_LOGIN')),
                'g_id' => $payloadAcc->sub,
                'photo' => '/storage/avatars/default.png',
                'is_active' => '1',
                'phone' => '-',
                'linkedin' => '-',
                'instagram' => '-',
                'twitter' => '-',
                'whatsapp' => '-',
                "deleted" => 0
            ]
        );

        // $tokenVerify = JWT::encode(['sub' => $user->id], env('JWT_SECRET'), env('JWT_ALG'));

        // // Mail handler to verify
        // Mail::to($payloadAcc->email)->send(new Verification($payloadAcc->name, $tokenVerify));

        // return response()->json([
        //     'data' => $user,
        // ], 201);

        $token = null;
        if ($isMobile ==  true) {
            $user->tokens()->where('name', 'auth_token_mobile')->delete();
            $token = $user->createToken('auth_token_mobile')->plainTextToken;
        } else {
            $user->tokens()->where('name', 'auth_token_web')->delete();
            $token = $user->createToken('auth_token_web')->plainTextToken;
        }
        return response()->json(
            [
                'data' => $user,
                'admin' => $user->admin()->first(),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ],
            200
        );
    }

    public function loginGoogle(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                'email' => 'required|string',
                'credential' => 'required|string'
            ]
        );
        // This credential is remaked from frontend by google one tap reponse. Not from google response credential
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }

        $user = User::where('email', $req->email)->first();
        if (!$user) {
            if (!filter_var($req->email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(["error" => "Email is not valid"], 403);
            }
            return $this->registerWithGoogle($req->credential, $req->is_mobile);
        } else {
            $payloadAcc = '';
            try {
                $payloadAcc = JWT::decode($req->credential, new Key(env('JWT_SECRET'), env('JWT_ALG')));
            } catch (\Throwable $th) {
                return response()->json(
                    [
                        'error' => 'Invalid signature credential',
                    ],
                    403
                );
            }

            if ($user->g_id != $payloadAcc->sub || $user->is_active != '1') {
                if($user->g_id === "-"){
                    User::where('id', $user->id)->update([
                        'g_id' => $payloadAcc->sub,
                        'is_active' => '1'
                    ]);
                }else{
                    return response()->json(['error' => 'Unauthorized, your account is not active. Or not registered'], 401);
                }
            }
            if ($user->deleted === 1) {
                return response()->json(["error" => "This account has removed"], 404);
            }
            $token = null;
            if ($req->is_mobile ==  true) {
                $user->tokens()->where('name', 'auth_token_mobile')->delete();
                $token = $user->createToken('auth_token_mobile')->plainTextToken;
            } else {
                $user->tokens()->where('name', 'auth_token_web')->delete();
                $token = $user->createToken('auth_token_web')->plainTextToken;
            }
            return response()->json(
                [
                    'data' => $user,
                    'admin' => $user->admin()->first(),
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ],
                200
            );
        }
    }

    private function registerWithOtp($email, $otp)
    {
        $name = explode('@', $email)[0];
        $user = User::create(
            [
                'f_name' => $name,
                'l_name' => '-',
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(env('SECRET_PASS_BACKDOOR_OTP_LOGIN')),
                'g_id' => '-',
                'photo' => '/storage/avatars/default.png',
                'is_active' => '0',
                'phone' => '-',
                'linkedin' => '-',
                'instagram' => '-',
                'twitter' => '-',
                'whatsapp' => '-',
                "deleted" => 0
            ]
        );
        Otp::create(
            [
                'user_id' => $user->id,
                'otp_code' => $otp,
            ]
        );
        return $user;
    }

    public function generateOtp($email, $forLogin = true, $data = null)
    {
        $otp = Str::password(8, true, true, false);
        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = $this->registerWithOtp($email, $otp);
        } else {
            $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
            $updated = Otp::where('user_id', $user->id)->update(
                [
                    'otp_code' => $otp,
                    'exp_to_verify' => $now->add(new DateInterval('PT2M')),
                ]
            );
            if ($updated == 0) {
                Otp::create(
                    [
                        'user_id' => $user->id,
                        'otp_code' => $otp,
                        'exp_to_verify' => $now->add(new DateInterval('PT2M')),
                    ]
                );
            }
        }
        if ($forLogin) {
            try {
                Mail::to($email)->send(new Verification($email, $otp, true));
            } catch (\Throwable $th) {
                ResendTrxNotification::writeErrorLog('App\Mail\Verification', "verification", [$email, $otp, true], $email);
            }
            return ["message" => "check your email to get the OTP code"];
        } else {
            try {
                Mail::to($email)->send(new VerificationBank($data["code_bank"], $data["acc_number"], $otp));
            } catch (\Throwable $th) {
                ResendTrxNotification::writeErrorLog('App\Mail\VerificationBank', "bank verification", [$data["code_bank"], $data["acc_number"], $otp], $email);
            }
            return ["message" => "check your email to get the OTP code"];
        }
    }

    public function loginWithOtp(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                "email" => 'required|string'
            ]
        );
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        if (!filter_var($req->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(["error" => "Email is not valid"], 403);
        }
        $msg = $this->generateOtp($req->email);
        return response()->json($msg, 200);
    }

    public function verifyOtp(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                "email" => "required|string",
                "otp_code" => "required|string"
            ]
        );
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $user = User::where('email', $req->email);
        if (!$user->first()) {
            return response()->json(["error" => "User data not found"], 404);
        }
        if ($user->deleted === 1) {
            return response()->json(["error" => "This account has removed"], 404);
        }
        $otp = $user->first()->otp()->first();
        if (!$otp) {
            return response()->json(["error" => "OTP code is not found"]);
        }
        if ($otp->otp_code != $req->otp_code) {
            return response()->json(["error" => "OTP code is not valid"]);
        }
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $exp = new DateTime($otp->exp_to_verify, new DateTimeZone('Asia/Jakarta'));
        if($now > $exp){
            $this->generateOtp($req->email);
            return response()->json(["message" => "Your OTP has expired. You will receive new OTP for login"], 403);
        }
        $user->update(
            [
                "is_active" => '1'
            ]
        );
        $user = $user->first();
        $token = null;
        if ($req->is_mobile ==  true) {
            $user->tokens()->where('name', 'auth_token_mobile')->delete();
            $token = $user->createToken('auth_token_mobile')->plainTextToken;
        } else {
            $user->tokens()->where('name', 'auth_token_web')->delete();
            $token = $user->createToken('auth_token_web')->plainTextToken;
        }
        return response()->json(
            [
                "data" => $user,
                'admin' => $user->admin()->first(),
                "access_token" => $token,
                "token_type" => "Bearer"
            ]
        );
    }

    //logout account
    public function logout(Request $req)
    {
        $nameToken = $req->is_mobile == true ? 'auth_token_mobile' : 'auth_token_web';
        Auth::user()->tokens()->where('name', $nameToken)->delete();
        return response()->json(['message' => 'Logout success'], 200);
    }

    //Verify account
    public function verify($subId, $redirect=null)
    {
        $payload = '';
        try {
            $payload = JWT::decode($subId, new Key(env('JWT_SECRET'), env('JWT_ALG')));
        } catch (\Throwable $th) {
            // return response()->json(
            //     [
            //         'error' => 'Invalid signature credential',
            //     ],
            //     403
            // );
            return redirect()->to(env("FRONTEND_URL") . "/auth-user");
        }
        User::where('id', $payload->sub)->update(
            [
                'is_active' => '1'
            ]
        );
        // $user = User::where('id', $payload->sub)->first();
        // if(!$user){
        //     return redirect()->to(env("FRONTEND_URL") . "/auth-user");
        // }
        // if ($user->deleted === 1) {
        //     return redirect()->to(env("FRONTEND_URL") . "/auth-user");
        //     // return response()->json(["error" => "This account has removed"], 404);
        // }
        // NOTE : Replace response with redirect to ReactApp
        // return response()->json(
        //     [
        //         'updated' => $user,
        //         'message' => $user == 0 ? 'User account not found' : 'User account has been updated'
        //     ],
        //     $user == 0 ? 404 : 200
        // );
        return redirect()->to($redirect ? env("FRONTEND_URL") . '/auth-user?redirect_to=' . $redirect : env("FRONTEND_URL") ."/auth-user");
    }

    //forget password (send email)
    public function requestResetPass(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                'email' => 'required'
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }

        $user = User::where('email', $req->email)->first();
        if (!$user) {
            return response()->json(['error' => 'email account not found'], 404);
        }
        $status = Password::sendResetLink($req->only('email'));
        return response()->json(["data" => __($status)], $status === Password::RESET_LINK_SENT ? 202 : 403);
    }

    //reset password
    public function resetPassword(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            [
                'token' => 'required',
                'email' => "required",
                'password' => 'required|min:8|confirmed',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->fails(), 403);
        }
        if ($req->password != $req->password_confirmation) {
            return response()->json(['error' => 'Please check your new password and confirm new password again'], 403);
        }

        $status = Password::reset(
            $req->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password){
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        return response()->json(["data" => __($status)], $status === Password::PASSWORD_RESET ? 202 : 403);
    }
}
