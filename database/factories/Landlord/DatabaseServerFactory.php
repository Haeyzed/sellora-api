<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Tenancy\Models\DatabaseServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A server in the pool. By default it points at the central database server, which is the only one tests have.
 *
 * @extends Factory<DatabaseServer>
 */
final class DatabaseServerFactory extends Factory
{
    protected $model = DatabaseServer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'region' => config()->array('platform.regions')[0],
            'host' => config()->string('database.connections.central.host'),
            'port' => (int) config('database.connections.central.port'),
            'username' => config()->string('database.connections.central.username'),
            'password' => (string) config('database.connections.central.password'),
            'capacity' => 100,
            'accepting_new_tenants' => true,
        ];
    }

    public function inRegion(string $region): self
    {
        return $this->state(['region' => $region]);
    }
}
