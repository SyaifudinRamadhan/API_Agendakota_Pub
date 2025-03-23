<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class UserCtrl extends Controller
{
    public function updateProfile(Request $req, $userId = null)
    {
        $validator = Validator::make(
            $req->all(),
            $userId ? [
                'f_name' => 'required|string',
                'l_name' => 'required|string',
                'name' => 'required|string',
                'email' => 'required|string',
                'photo' => 'image|max:2048',
                'phone' => 'required|string',
                'linkedin' => 'required|string',
                'instagram' => 'required|string',
                'twitter' => 'required|string',
                'whatsapp' => 'required|string',
            ] : [
                'f_name' => 'required|string',
                'l_name' => 'required|string',
                'name' => 'required|string',
                'email' => 'required|string',
                'photo' => 'image|max:2048',
                'phone' => 'required|string',
                'linkedin' => 'required|string',
                'instagram' => 'required|string',
                'twitter' => 'required|string',
                'whatsapp' => 'required|string',
                'interest' => 'required|array'
            ]
        );
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }
        $user = '';
        if ($userId == null) {
            $user = Auth::user();
        } else {
            $user = User::where('id', $userId)->first();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
        }

        $namePhoto = $user->photo;
        if ($req->hasFile('photo')) {
            // $originName = pathinfo($req->file('photo')->getClientOriginalName(), PATHINFO_FILENAME);
            $namePhoto = BasicFunctional::randomStr(5) . '_' . time() . '.' . $req->file('photo')->getClientOriginalExtension();
            $req->file('photo')->storeAs('public/avatars', $namePhoto);
            $namePhoto = '/storage/avatars/' . $namePhoto;
            // Remove last image
            if ($user->photo != '/storage/avatars/default.png') {
                $fileName = explode('/', $user->photo);
                Storage::delete('public/avatars/' . $fileName[3]);
            }
        }
        $interest = '';
        if(!$userId){
            foreach ($req->interest as $value) {
                if ($interest === '') {
                    $interest = $value;
                } else {
                    $interest .= '~^|-|^~' . $value;
                }
            }
        }

        $updated = User::where('id', $user->id)->update(
            $userId ? [
                'f_name' => $req->f_name,
                'l_name' => $req->l_name,
                'name' => $req->name,
                'email' => $req->email,
                'photo' => $namePhoto,
                'phone' => $req->phone,
                'linkedin' => $req->linkedin,
                'instagram' => $req->instagram,
                'twitter' => $req->twitter,
                'whatsapp' => $req->whatsapp,
            ] : [
                'f_name' => $req->f_name,
                'l_name' => $req->l_name,
                'name' => $req->name,
                'email' => $req->email,
                'photo' => $namePhoto,
                'phone' => $req->phone,
                'linkedin' => $req->linkedin,
                'instagram' => $req->instagram,
                'twitter' => $req->twitter,
                'whatsapp' => $req->whatsapp,
                'interest' => $interest,
            ]
        );
        return response()->json(['updated' => $updated, 'user' => User::where('id', $user->id)->first()], $updated == 0 ? 404 : 202);
    }

    public function updatePassword(Request $req, $userId = null)
    {
        $ruleValidate = [
            'new_password' => 'required|string|min:8',
            'confirm_password' => 'required|string|min:8'
        ];
        $accesUser = false;
        $needLastPass = false;
        $user = '';
        if ($userId == null) {
            $user = Auth::user();
            // check password is default ?
            if (!password_verify(env('SECRET_PASS_BACKDOOR_GOOGLE_LOGIN'), $user->password) && !password_verify(env('SECRET_PASS_BACKDOOR_OTP_LOGIN'), $user->password)) {
                $ruleValidate += [
                    'last_password' => 'required|string',
                ];
                $needLastPass = true;
            }
            $accesUser = true;
        } else {
            $user = User::where('id', $userId)->first();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
        }

        $validator = Validator::make($req->all(), $ruleValidate);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 403);
        }

        if ($accesUser && $needLastPass) {
            if (password_verify($req->last_password, $user->password) == false) {
                return response()->json(["error" => "Unauthorized last password"], 401);
            }
        }
        if ($req->new_password !== $req->confirm_password) {
            return response()->json(["error" => "Your confirm passsword is not match"], 403);
        }
        $updated = User::where('id', $user->id)->update(["password" => Hash::make($req->new_password)]);
        return response()->json(["updated" => $updated], $updated == 0 ? 404 : 202);
    }

    public function setTwoFactor(Request $req)
    {
        $user = Auth::user();
        User::where('id', $user->id)->update(['two_factor' =>  $req->enable_two_factor == true ? 1 : 0]);
        return response()->json(["messsage" => "Two Factor Auth has " . $req->enable_two_factor == true ? "enabled" : "disabled"], 202);
    }

    public function getUser($userId = null)
    {
        $user = '';
        if ($userId == null) {
            $user = Auth::user();
        } else {
            $user = User::where('id', $userId)->first();
            if (!$user) {
                return response()->json(["error" => "User by ID not found"], 404);
            }
        }
        return response()->json(["user" => $user], 200);
    }

    public function deleteUser($userId)
    {
        $user = User::where('id', $userId)->update(["deleted" => 1]);
        return response()->json(['deleted' => $user], $user == 0 ? 404 : 202);
    }

    public function hardDeleteUser($userId)
    {
        $user = User::where('id', $userId);
        if (explode('/', $user->first()->photo)[3] !== 'default.png') {
            Storage::delete('public/avatars/' . explode('/', $user->first()->photo)[3]);
        }
        return response()->json(['deleted' => $user], $user == 0 ? 404 : 202);
    }

    public function setActive($userId)
    {
        $updated = User::where('id', $userId)->update(["is_active" => "1"]);
        return response()->json(["updated" => $updated], $updated == 0 ? 404 : 200);
    }

    public function getBack($userId)
    {
        $updated = User::where('id', $userId)->update(["deleted" => 0]);
        return response()->json(["updated" => $updated], $updated == 0 ? 404 : 200);
    }
}
