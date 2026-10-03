<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PolicyPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyPageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, PolicyPage $policyPage): JsonResponse
    {
        abort_unless(in_array($policyPage->slug, PolicyPage::SLUGS, true) && $policyPage->published_at !== null, 404);
        $locale = $request->query('locale', 'ar');
        abort_unless(in_array($locale, ['ar', 'en'], true), 404);
        $body = $locale === 'ar' ? $policyPage->body_ar : $policyPage->body_en;
        abort_if(blank($body), 404);

        return response()->json(['data' => [
            'slug' => $policyPage->slug,
            'locale' => $locale,
            'body' => $body,
            'version' => $policyPage->version,
            'published_at' => $policyPage->published_at->toIso8601String(),
        ]]);
    }
}
