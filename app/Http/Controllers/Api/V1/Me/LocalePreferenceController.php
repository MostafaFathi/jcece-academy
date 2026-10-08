<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalePreferenceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $attributes = $request->validate(['locale' => ['required', 'in:ar,en']]);
        $request->user()->update(['preferred_locale' => $attributes['locale']]);

        return response()->json(['preferred_locale' => $attributes['locale']]);
    }
}
