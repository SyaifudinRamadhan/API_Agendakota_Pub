<?php

namespace App\Http\Middleware;

use App\Models\AccessHistory;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\ASN1\Maps\RSAPrivateKey;
use Symfony\Component\HttpFoundation\Response;

class ApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // =============== Hardcode for ID Client and API key decyptor - encryptor =====================
        // NB :
        // 1. API Key is code that hashed from payload with content bellow
        // 2. Hash algorihtm using SHA 256 
        // 3. Hash key must in the future will store to api_client database table
        // 4. Stucture bellow, is a layout of api_client database table
        $client = [
            "id" => "3f0db6e0-c0c8-4ce8-a3ca-1fc7a9a7b053",
            "email" => "halo@agendakota.id",
            "username" => "agendakota",
            "password" => "********",
            // ... more again 
            "secret_key" => "SECRET_SIGNATURE"
        ];

        // ==============================================================================================

        // ========================== Structure of payload must be encrypt in client ====================
        /*

            JSON = {
                "client_id": "string client id",
                "email_user": "email user access" (Optional if in route after login)
                "timestamp": "unique integer timestamp when front end access API"
            }

        */
        // ==============================================================================================

        $apiToken = $request->header(('x-api-key'));
        $auth = $request->header(('Authorization'));
        if (!$apiToken) {
            $apiToken = $request->route()->parameter('xApiToken');
            if (!$apiToken) {
                return response()->json(["error" => "Please input API token in header"], 403);
            }
        }

        $decrypt = null;
        try {
            $decrypt = JWT::decode($apiToken, new Key(env('SECRET_SIGNATURE'), 'HS256'));
        } catch (\Throwable $th) {
            //throw $th;
            Log::info("Error decrypt API Key from ".$request->ip()." with url access is : ".$request->url());
            return response()->json(["error" => "Denied unauthentication client"], 403);
        }

        Log::info( "API access Stage I Decrypt data : " . json_encode($decrypt). " IP Address : ".$request->ip());
       
        if(!property_exists($decrypt, "client_id") || !property_exists($decrypt, "timestamp") || $decrypt->client_id === "" || $decrypt->timestamp === ""){
            return response()->json(["error" => "Denied unauthentication client"], 403);
        }

        Log::info( "API access Stage II check content : " . json_encode([
            "exits_email_user" => property_exists($decrypt, "email_user"),
            "auth" => $auth,
            "emthod" => $request->method()
        ]));

        if(property_exists($decrypt, "email_user") && $decrypt->email_user !== "" && $auth && $request->method() !== "GET"){
            $access = AccessHistory::where('client_id', $decrypt->client_id)->where('email', $decrypt->email_user)->where('timestamp_access', intval($decrypt->timestamp))->first();
            Log::info( "API access Stage III access auth data : " . json_encode([
                "same_access" => $access
            ]));
            if($access){
                return response()->json(["error" => "Denied unauthentication client"], 403);
            }
            $update = AccessHistory::where('client_id', $decrypt->client_id)->where('email', $decrypt->email_user)->update([
                'timestamp_access' => intval($decrypt->timestamp)
            ]);
            if($update == 0){
                AccessHistory::create([
                    'client_id' => $decrypt->client_id,
                    'email' => $decrypt->email_user,
                    'timestamp_access' => intval($decrypt->timestamp)
                ]);
            }
            return $next($request);
        } else if ((!property_exists($decrypt, "email_user") || $decrypt->email_user === "") && $auth) {
            return response()->json(["error" => "Denied unauthentication client"], 401);
        } else{
            return $next($request);
        }
    }
}
