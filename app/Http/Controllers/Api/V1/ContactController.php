<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['prohibited'],
        ]);

        ContactMessage::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'subject' => $attributes['subject'],
            'message' => $attributes['message'],
            'locale' => app()->getLocale() === 'en' ? 'en' : 'ar',
        ]);

        return response()->json(['status' => 'received'], 201);
    }
}
