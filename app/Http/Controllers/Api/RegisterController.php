<?php

namespace App\Http\Controllers\Api;

use App\Constants\ApiResponseType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    //
    public function login(Request $request)
    {
        $credential = $this->validate($request, [
            'email'=>['required'],
            'password'=>['required']
        ]);
        $user=User::withTrashed()->where('email', $credential['email'])->first();
        if (blank($user)) {
            return response()->json([
                'status' => ApiResponseType::MODEL_NOT_FOUND,
            ]);
        }
        $matchedPassword = Hash::check($credential['password'], $user->password);
        if (!$matchedPassword) {
            return response()->json([
                'status' => ApiResponseType::INVALID_CREDENTIAL,
            ]);
        }
        if (filled($user->deleted_at)) {
            return response()->json([
                'status' => ApiResponseType::APPROVAL_NEEDED,
            ]);
        }
        $token=($user)->createToken('personalToken')->plainTextToken;
        return response()->json([
            'status'=>ApiResponseType::SUCCESS,
            'token' => $token,
            'user'   => $user->load('roles.permissions'),
        ]);
    }

    public function logout(Request $request)
    {
        (\auth()->user())->tokens()->delete();
        return response()->json([
            'status'=>ApiResponseType::SUCCESS,
            'data' => true,
            'message' => 'Logout success'
        ]);
    }
}
