<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(LoginRequest $request): AuthenticatedUserResource
    {
        $authenticated = Auth::guard('web')->attempt([
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'status' => UserStatus::Active->value,
        ], $request->boolean('remember'));

        if (! $authenticated) {
            throw ValidationException::withMessages(['email' => 'The provided credentials do not match our records.']);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        return new AuthenticatedUserResource($user->load('instructorProfile'));
    }

    public function show(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($request->user()->load('instructorProfile'));
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return response()->json(status: 204);
    }
}
