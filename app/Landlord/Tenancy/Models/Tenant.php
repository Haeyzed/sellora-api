<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\TenantDatabaseConfig;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Landlord\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;

/**
 * A store on the platform. Each store has its own database and one or more domains.
 *
 * Lives in the central database. Store data itself never lives here; only
 * what running the platform needs: the store's name, status, hosting region,
 * the owner's contact and the defaults chosen at registration.
 *
 * @property string $id
 * @property string $public_id
 * @property string $name
 * @property TenantStatus $status
 * @property string $hosting_region Where its database, files and backups live, such as "eu". Fixed after registration.
 * @property int|null $database_server_id Null until the store is placed on a server.
 * @property string $owner_name
 * @property string $owner_email
 * @property string $country_code ISO 3166-1 alpha-2.
 * @property string $currency_code ISO 4217, the default from the country.
 * @property string $timezone
 * @property string $locale
 * @property CarbonImmutable|null $provisioned_at
 * @property array<string, mixed>|null $data
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DatabaseServer|null $databaseServer
 */
final class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasPublicId;

    /**
     * The real columns of the tenants table. Anything else is kept in the "data" JSON column by stancl/tenancy.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'public_id',
            'name',
            'status',
            'hosting_region',
            'database_server_id',
            'owner_name',
            'owner_email',
            'country_code',
            'currency_code',
            'timezone',
            'locale',
            'provisioned_at',
        ];
    }

    /**
     * The database connection settings, pointed at the server the store was placed on.
     */
    public function database(): DatabaseConfig
    {
        return new TenantDatabaseConfig($this);
    }

    /**
     * @return BelongsTo<DatabaseServer, $this>
     */
    public function databaseServer(): BelongsTo
    {
        return $this->belongsTo(DatabaseServer::class);
    }

    /**
     * The store's address on the platform domain, such as "mystore.sellora.com".
     */
    public static function platformDomainFor(string $subdomain): string
    {
        return $subdomain.'.'.config()->string('platform.domain');
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'provisioned_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
