<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Tenant\Delivery\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
final class DriverFactory extends Factory
{
    protected $model = Driver::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+23480'.fake()->unique()->numerify('########'),
            'pin' => '123456',
            'is_active' => true,
        ];
    }

    public function deactivated(): self
    {
        return $this->state(['is_active' => false]);
    }
}
