<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdatePolicyPageRequest;
use App\Models\PolicyPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PolicyPageController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PolicyPage::class);

        return response()->json(['data' => PolicyPage::query()->whereIn('slug', PolicyPage::SLUGS)->orderBy('id')->get()]);
    }

    public function update(UpdatePolicyPageRequest $request, PolicyPage $policyPage): JsonResponse
    {
        abort_unless(in_array($policyPage->slug, PolicyPage::SLUGS, true), 404);
        Gate::authorize('update', $policyPage);
        $policyPage->update($request->safe()->only(['draft_ar', 'draft_en']));

        return response()->json(['data' => $policyPage->refresh()]);
    }

    public function publish(PolicyPage $policyPage): JsonResponse
    {
        abort_unless(in_array($policyPage->slug, PolicyPage::SLUGS, true), 404);
        Gate::authorize('publish', $policyPage);

        DB::transaction(function () use ($policyPage): void {
            $policyPage = PolicyPage::query()->whereKey($policyPage->getKey())->lockForUpdate()->firstOrFail();
            if (blank($policyPage->draft_ar) || blank($policyPage->draft_en)) {
                throw ValidationException::withMessages(['draft_ar' => 'Approved Arabic and English text are required before publication.']);
            }
            $policyPage->forceFill([
                'body_ar' => $policyPage->draft_ar,
                'body_en' => $policyPage->draft_en,
                'version' => $policyPage->version + 1,
                'published_at' => now(),
            ])->save();
        });

        return response()->json(['data' => $policyPage->refresh()]);
    }
}
