<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Identity\Models\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAdmin>
 */
final class PlatformAdminFactory extends Factory
{
    protected $model = PlatformAdmin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password@123',
            'is_active' => true,
        ];
    }

    public function deactivated(): self
    {
        return $this->state(['is_active' => false]);
    }
}
