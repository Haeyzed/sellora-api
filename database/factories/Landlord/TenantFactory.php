<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An active store's central record. Creating it doesn't create its database; tests use createStore() for that.
 *
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => fake()->company(),
            'status' => TenantStatus::Active,
            'hosting_region' => array_key_first(config()->array('platform.regions')),
            'owner_name' => fake()->name(),
            'owner_email' => fake()->unique()->safeEmail(),
            'country_code' => 'NG',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
            'locale' => 'en',
            'provisioned_at' => now(),
        ];
    }

    /**
     * A store still being set up.
     */
    public function provisioning(): self
    {
        return $this->state(['status' => TenantStatus::Provisioning, 'provisioned_at' => null]);
    }
}
