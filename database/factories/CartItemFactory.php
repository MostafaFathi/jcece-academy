<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Course;
use App\Models\Package;
use App\PurchasableType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'purchasable_type' => PurchasableType::Course->value,
            'purchasable_id' => Course::factory(),
        ];
    }

    public function forPackage(?Package $package = null): static
    {
        return $this->state(fn (): array => [
            'purchasable_type' => PurchasableType::Package->value,
            'purchasable_id' => $package ?? Package::factory(),
        ]);
    }
}
