<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
final class StaffMemberFactory extends Factory
{
    protected $model = StaffMember::class;

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
