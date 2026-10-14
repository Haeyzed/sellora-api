<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Tenant\Customers\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerGroup>
 */
final class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
        ];
    }
}
