<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Tenant\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ['en' => fake()->unique()->words(3, true)],
        ];
    }
}
