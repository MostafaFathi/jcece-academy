<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $canonicalEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->value('email');

        try {
            Password::sendResetLink(['email' => $canonicalEmail ?? $email]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json(['message' => __('auth.reset_sent_neutral')], 202);
    }
}
