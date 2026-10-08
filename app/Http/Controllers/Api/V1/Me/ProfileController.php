<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuthenticatedUserResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($request->user()->load('instructorProfile'));
    }

    public function update(Request $request): AuthenticatedUserResource
    {
        $editable = ['name', 'phone', 'country', 'city', 'specialization'];
        if (array_diff(array_keys($request->all()), $editable) !== []) {
            throw ValidationException::withMessages(['profile' => __('validation.profile_fields')]);
        }

        $attributes = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'specialization' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $request->user()->update($attributes);

        return new AuthenticatedUserResource($request->user()->refresh()->load('instructorProfile'));
    }
}
