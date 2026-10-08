<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\Models\User;
use App\RoleName;
use App\Services\TransactionalDeliveryService;
use App\UserStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RegistrationController extends Controller
{
    public function __invoke(RegisterRequest $request, TransactionalDeliveryService $deliveries): JsonResponse
    {
        $attributes = $request->safe()->only(['name', 'email', 'password', 'locale']);

        try {
            $user = DB::transaction(function () use ($attributes, $deliveries): User {
                $user = User::create([
                    'name' => $attributes['name'], 'email' => $attributes['email'], 'password' => $attributes['password'],
                    'preferred_locale' => $attributes['locale'] ?? 'ar', 'status' => UserStatus::Active,
                ]);
                $user->assignRole(Role::findOrCreate(RoleName::Student->value));
                $deliveries->recordForUser('welcome', 'User', $user->id, $user);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => __('validation.attributes.email')])]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return (new AuthenticatedUserResource($user->load('roles')))->response()->setStatusCode(201);
    }
}
