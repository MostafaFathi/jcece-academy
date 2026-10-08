<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'different:current_password', Password::defaults(), 'confirmed'],
        ]);

        Auth::guard('web')->logoutOtherDevices($attributes['current_password']);
        $user = $request->user();
        $user->update(['password' => $attributes['password']]);

        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))->where('user_id', $user->id);
            if ($request->hasSession()) {
                $sessions->where('id', '!=', $request->session()->getId());
            }
            $sessions->delete();
        }

        $user->tokens()->delete();

        return response()->json(['status' => 'password_changed']);
    }
}
