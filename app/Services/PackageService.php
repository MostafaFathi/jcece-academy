<?php

namespace App\Services;

use App\Models\Package;
use App\PackageStatus;

class PackageService
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Package
    {
        return $this->persist(new Package, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Package $package, array $attributes): Package
    {
        return $this->persist($package, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function persist(Package $package, array $attributes): Package
    {
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
