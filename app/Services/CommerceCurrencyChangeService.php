<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Package;
use App\Models\SiteSetting;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommerceCurrencyChangeService
{
    public function __construct(private AuditTrail $audit, private CommerceCatalogService $catalog) {}

    /** @return array<int, string> */
    public function catalogCurrencies(): array
    {
        $currencies = collect();
        foreach ([Course::class, Package::class, Coupon::class] as $model) {
            $query = $model::query();
            if ($model !== Coupon::class) {
                $query->withTrashed();
            }
            if ((clone $query)->whereNull('currency')->exists()) {
                $currencies->push($this->catalog->legacyCurrency());
            }
            $currencies = $currencies->merge($query->whereNotNull('currency')->distinct()->pluck('currency'));
        }

        return $currencies->filter()->unique()->sort()->values()->all();
    }

    /**
     * @param  array<string, string>  $rates
     * @return array{currency: string, scope: string, converted: array{courses: int, packages: int, coupons: int}}
     */
    public function change(string $targetCurrency, string $scope, array $rates, User $actor): array
    {
        return DB::transaction(function () use ($targetCurrency, $scope, $rates, $actor): array {
            SiteSetting::current();
            $settings = SiteSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $previousCurrency = $settings->currency ?? $this->catalog->currency();
            $counts = ['courses' => 0, 'packages' => 0, 'coupons' => 0];

            if ($scope === 'all') {
                foreach ([Course::class => 'courses', Package::class => 'packages', Coupon::class => 'coupons'] as $model => $key) {
                    $query = $model::query();
                    if ($model !== Coupon::class) {
                        $query->withTrashed();
                    }
                    foreach ($query->orderBy('id')->lockForUpdate()->cursor() as $item) {
                        $source = $item->currency ?? $this->catalog->legacyCurrency();
                        if ($source === $targetCurrency) {
                            continue;
                        }
                        $rate = $rates[$source] ?? null;
                        if ($rate === null || preg_match('/^\d{1,10}(?:\.\d{1,8})?$/', $rate) !== 1 || BigDecimal::of($rate)->isLessThanOrEqualTo(0)) {
                            throw ValidationException::withMessages(['rates.'.$source => "A positive conversion rate is required for {$source}."]);
                        }
                        $this->convertItem($item, $rate);
                        $item->currency = $targetCurrency;
                        $item->save();
                        $counts[$key]++;
                    }
                }
            }

            $settings->update(['currency' => $targetCurrency]);
            $this->audit->record('commerce.currency_changed', $settings, $actor, [
                'from_currency' => $previousCurrency,
                'to_currency' => $targetCurrency,
                'scope' => $scope,
                'rates' => json_encode($scope === 'all' ? $rates : [], JSON_THROW_ON_ERROR),
                'changed_fields' => json_encode($counts, JSON_THROW_ON_ERROR),
            ]);

            return ['currency' => $targetCurrency, 'scope' => $scope, 'converted' => $counts];
        }, 3);
    }

    private function convertItem(Model $item, string $rate): void
    {
        $columns = $item instanceof Coupon
            ? ($item->discount_type === 'fixed' ? ['discount_value', 'minimum_order_amount'] : ['minimum_order_amount'])
            : ['price', 'compare_price', 'promotional_price'];

        foreach ($columns as $column) {
            if ($item->{$column} === null) {
                continue;
            }
            $converted = BigDecimal::of((string) $item->{$column})->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp);
            if ($converted->isGreaterThan('9999999999.99') || ($item instanceof Coupon && $column === 'discount_value' && $converted->isLessThanOrEqualTo(0))) {
                throw ValidationException::withMessages(['rates' => 'The rate produces an unsupported amount. No currency values were changed.']);
            }
            $item->{$column} = (string) $converted;
        }
    }
}
