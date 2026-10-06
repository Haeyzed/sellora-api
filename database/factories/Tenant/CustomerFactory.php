<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Tenant\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password@123',
            'is_active' => true,
        ];
    }

    public function deactivated(): self
    {
        return $this->state(['is_active' => false]);
    }
}
