<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use App\Models\User;
use App\RoleName;
use App\UserStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RegistrationController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $attributes = $request->safe()->only(['name', 'email', 'password']);

        try {
            $user = DB::transaction(function () use ($attributes): User {
                $user = User::create([...$attributes, 'status' => UserStatus::Active]);
                $user->assignRole(Role::findOrCreate(RoleName::Student->value));

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'The email has already been taken.']);
        }

        return (new AuthenticatedUserResource($user->load('roles')))->response()->setStatusCode(201);
    }
}
