<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteFaq;
use App\Models\SitePage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteContentController extends Controller
{
    public function page(Request $request, SitePage $sitePage): JsonResponse
    {
        abort_unless(in_array($sitePage->slug, SitePage::SLUGS, true) && $sitePage->published_at !== null, 404);
        $locale = $request->header('X-Locale') === 'en' ? 'en' : 'ar';
        $body = $sitePage->{'body_'.$locale};
        abort_if(blank($body), 404);

        return response()->json(['data' => ['slug' => $sitePage->slug, 'body' => $body, 'locale' => $locale]]);
    }

    public function faqs(Request $request): JsonResponse
    {
        $locale = $request->header('X-Locale') === 'en' ? 'en' : 'ar';
        $items = SiteFaq::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (SiteFaq $faq): array => [
                'id' => $faq->id,
                'question' => $faq->{'question_'.$locale},
                'answer' => $faq->{'answer_'.$locale},
            ]);

        return response()->json(['data' => $items]);
    }
}
