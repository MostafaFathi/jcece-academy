<?php

namespace App\Services;

use App\CourseStatus;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Package;
use App\Models\SiteSetting;
use App\PackageStatus;
use App\PurchasableType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CommerceCatalogService
{
    public function currency(): ?string
    {
        $currency = strtoupper((string) (SiteSetting::query()->value('currency') ?? $this->legacyCurrency()));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    public function lockedCurrency(): ?string
    {
        SiteSetting::current();
        $currency = strtoupper((string) (SiteSetting::query()->whereKey(1)->lockForUpdate()->value('currency') ?? config('jcec.commerce.currency')));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    public function productCurrency(Course|Package $product): ?string
    {
        $currency = strtoupper((string) ($product->currency ?? $this->legacyCurrency()));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    public function couponCurrency(Coupon $coupon): ?string
    {
        $currency = strtoupper((string) ($coupon->currency ?? $this->legacyCurrency()));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    public function legacyCurrency(): ?string
    {
        $currency = strtoupper((string) config('jcec.commerce.currency'));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    public function findPurchasable(PurchasableType $type, int $id, bool $lockForUpdate = false): Course|Package
    {
        /** @var Builder<Course|Package> $query */
        $query = $type->modelClass()::query()
            ->where('status', $type === PurchasableType::Course ? CourseStatus::Published : PackageStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $product = $query->find($id);

        if (! $this->isPurchasable($product)) {
            throw ValidationException::withMessages([
                'purchasable_id' => 'The selected product is not currently available for purchase.',
            ]);
        }

        return $product;
    }

    public function isPurchasable(Course|Package|null $product): bool
    {
        if ($product === null || $product->published_at === null || $product->published_at->isFuture()) {
            return false;
        }

        return match (true) {
            $product instanceof Course => $product->status === CourseStatus::Published,
            $product instanceof Package => $product->status === PackageStatus::Published
                && (! $product->is_sequential || $product->sequential_completion_percentage !== null),
        };
    }

    public function typeFor(Course|Package $product): PurchasableType
    {
        return $product instanceof Course ? PurchasableType::Course : PurchasableType::Package;
    }
}
