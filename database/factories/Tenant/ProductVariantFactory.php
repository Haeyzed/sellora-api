<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price' => Money::ofMinor(fake()->numberBetween(100, 100_000), 'NGN'),
            'requires_shipping' => true,
            'weight_grams' => fake()->numberBetween(50, 5000),
        ];
    }
}
