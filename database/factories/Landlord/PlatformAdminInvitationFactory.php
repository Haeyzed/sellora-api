<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Identity\Models\PlatformAdminInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformAdminInvitation>
 */
final class PlatformAdminInvitationFactory extends Factory
{
    protected $model = PlatformAdminInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(3),
        ];
    }

    /**
     * An invitation whose link is the given token.
     */
    public function withToken(string $token): self
    {
        return $this->state(['token_hash' => hash('sha256', $token)]);
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }
}
