<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\SiteFaq;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\PermissionName;
use App\Services\CommerceCatalogService;
use App\Services\CommerceCurrencyChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteContentController extends Controller
{
    public function index(Request $request, CommerceCatalogService $catalog, CommerceCurrencyChangeService $currencyChanges): JsonResponse
    {
        $this->authorizeManage($request);
        foreach (SitePage::SLUGS as $slug) {
            SitePage::query()->firstOrCreate(['slug' => $slug]);
        }

        return response()->json(['data' => [
            'pages' => SitePage::query()->whereIn('slug', SitePage::SLUGS)->orderBy('slug')->get(),
            'faqs' => SiteFaq::query()->orderBy('sort_order')->orderBy('id')->get(),
            'settings' => [
                'home_autoplay_enabled' => (bool) (SiteSetting::query()->value('home_autoplay_enabled') ?? true),
                'currency' => $catalog->currency(),
                'catalog_currencies' => $currencyChanges->catalogCurrencies(),
                'can_convert_currency' => $this->canConvertCurrency($request),
            ],
        ]]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $attributes = $request->validate(['home_autoplay_enabled' => ['required', 'boolean']]);
        $settings = SiteSetting::current();
        $settings->update($attributes);

        return response()->json(['data' => ['home_autoplay_enabled' => $settings->home_autoplay_enabled]]);
    }

    public function updateCurrency(Request $request, CommerceCurrencyChangeService $currencyChanges): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless($this->canConvertCurrency($request), 403);
        $attributes = $request->validate([
            'currency' => ['required', 'string', Rule::in(['ILS', 'USD', 'JOD', 'EUR', 'GBP', 'AED', 'SAR', 'EGP', 'TRY'])],
            'scope' => ['required', Rule::in(['all', 'future'])],
            'rates' => ['sometimes', 'array'],
            'rates.*' => ['required', 'string', 'regex:/^\d{1,10}(?:\.\d{1,8})?$/'],
        ]);

        return response()->json(['data' => $currencyChanges->change(
            $attributes['currency'],
            $attributes['scope'],
            $attributes['rates'] ?? [],
            $request->user(),
        )]);
    }

    public function updatePage(Request $request, string $slug): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless(in_array($slug, SitePage::SLUGS, true), 404);
        $attributes = $request->validate([
            'draft_ar' => ['nullable', 'string', 'max:20000'],
            'draft_en' => ['nullable', 'string', 'max:20000'],
        ]);
        $page = SitePage::query()->firstOrCreate(['slug' => $slug]);
        $page->update($attributes);

        return response()->json(['data' => $page->refresh()]);
    }

    public function publishPage(Request $request, string $slug): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless(in_array($slug, SitePage::SLUGS, true), 404);
        $page = SitePage::query()->firstOrCreate(['slug' => $slug]);
        if (blank($page->draft_ar) || blank($page->draft_en)) {
            throw ValidationException::withMessages(['draft_ar' => __('validation.required', ['attribute' => 'Arabic and English content'])]);
        }
        $page->update(['body_ar' => $page->draft_ar, 'body_en' => $page->draft_en, 'published_at' => now()]);

        return response()->json(['data' => $page->refresh()]);
    }

    public function storeFaq(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $faq = SiteFaq::query()->create($this->faqAttributes($request, true));

        return response()->json(['data' => $faq], 201);
    }

    public function updateFaq(Request $request, SiteFaq $siteFaq): JsonResponse
    {
        $this->authorizeManage($request);
        $siteFaq->update($this->faqAttributes($request, false));

        return response()->json(['data' => $siteFaq->refresh()]);
    }

    public function destroyFaq(Request $request, SiteFaq $siteFaq): JsonResponse
    {
        $this->authorizeManage($request);
        $siteFaq->delete();

        return response()->json(status: 204);
    }

    public function contacts(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json(ContactMessage::query()->latest()->paginate(20));
    }

    /** @return array<string, mixed> */
    private function faqAttributes(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'question_ar' => [$required, 'string', 'max:1000'],
            'answer_ar' => [$required, 'string', 'max:5000'],
            'question_en' => [$required, 'string', 'max:1000'],
            'answer_en' => [$required, 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(PermissionName::SiteContentManage->value), 403);
    }

    private function canConvertCurrency(Request $request): bool
    {
        return $request->user()?->can(PermissionName::CoursesUpdate->value)
            && $request->user()?->can(PermissionName::PackagesUpdate->value)
            && $request->user()?->can(PermissionName::CouponsManage->value);
    }
}
