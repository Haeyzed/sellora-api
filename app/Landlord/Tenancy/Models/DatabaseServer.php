<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Landlord\DatabaseServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A PostgreSQL server in the pool that hosts store databases, in one hosting region.
 *
 * New stores are placed on a server in their region that is accepting new
 * stores and has room, so the platform grows by adding servers, not code.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $region
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string $password Stored encrypted.
 * @property int $capacity How many store databases it may hold.
 * @property int $tenant_count How many it holds.
 * @property bool $accepting_new_tenants
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class DatabaseServer extends Model
{
    use CentralConnection;

    /** @use HasFactory<DatabaseServerFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'name',
        'region',
        'host',
        'port',
        'username',
        'password',
        'capacity',
        'accepting_new_tenants',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * The connection settings for this server: the central connection's settings with this server's address and credentials.
     *
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public function connectionConfig(array $template): array
    {
        return array_merge($template, [
            // A URL would override the host and credentials below.
            'url' => null,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
        ]);
    }

    protected static function newFactory(): DatabaseServerFactory
    {
        return DatabaseServerFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'capacity' => 'integer',
            'tenant_count' => 'integer',
            'accepting_new_tenants' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
