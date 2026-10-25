<?php

namespace App\Services;

use App\Models\Package;
use App\PackageStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageService
{
    public function __construct(private CommerceCatalogService $catalog) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Package
    {
        return DB::transaction(fn (): Package => $this->persist(new Package, $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(Package $package, array $attributes): Package
    {
        return DB::transaction(function () use ($package, $attributes): Package {
            $this->catalog->lockedCurrency();

            return $this->persist($package, $attributes);
        });
    }

    /** @param array<string, mixed> $attributes */
    private function persist(Package $package, array $attributes): Package
    {
        if (! $package->exists) {
            $attributes['currency'] = $this->catalog->lockedCurrency();
            if ($attributes['currency'] === null) {
                throw ValidationException::withMessages(['currency' => 'Configure a valid site currency before creating a package.']);
            }
        }
        if (! filter_var($attributes['is_sequential'] ?? $package->is_sequential, FILTER_VALIDATE_BOOLEAN)) {
            $attributes['sequential_completion_percentage'] = null;
        }

        if (
            ($attributes['status'] ?? null) === PackageStatus::Published->value
            && empty($attributes['published_at'])
            && $package->published_at === null
        ) {
            $attributes['published_at'] = now();
        }

        $package->fill($attributes)->save();

        return $package->refresh();
    }
}
