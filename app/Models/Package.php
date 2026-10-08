<?php

namespace App\Models;

use App\PackageStatus;
use App\PackageType;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Package memberships describe current catalog composition only.
 * Phase 5 commerce must copy the purchased course set into immutable,
 * order-item-specific snapshot rows when an order is created.
 */
#[Fillable(['title', 'slug', 'description', 'thumbnail', 'type', 'price', 'compare_price', 'promotional_price', 'discount_starts_at', 'discount_ends_at', 'access_duration_days', 'is_sequential', 'sequential_completion_percentage', 'status', 'published_at'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory, SoftDeletes;

    public function courseMemberships(): HasMany
    {
        return $this->hasMany(PackageCourse::class)->orderBy('sort_order')->orderBy('id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)
            ->as('membership')
            ->withPivot(['id', 'sort_order', 'is_required'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('package_courses.id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PackageType::class,
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'promotional_price' => 'decimal:2',
            'discount_starts_at' => 'datetime',
            'discount_ends_at' => 'datetime',
            'is_sequential' => 'boolean',
            'sequential_completion_percentage' => 'decimal:2',
            'status' => PackageStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
