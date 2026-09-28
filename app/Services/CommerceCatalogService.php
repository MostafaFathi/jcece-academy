<?php

namespace App\Services;

use App\CourseStatus;
use App\Models\Course;
use App\Models\Package;
use App\PackageStatus;
use App\PurchasableType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CommerceCatalogService
{
    public function currency(): ?string
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

        if ($product === null) {
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
            $product instanceof Package => $product->status === PackageStatus::Published,
        };
    }

    public function typeFor(Course|Package $product): PurchasableType
    {
        return $product instanceof Course ? PurchasableType::Course : PurchasableType::Package;
    }
}
