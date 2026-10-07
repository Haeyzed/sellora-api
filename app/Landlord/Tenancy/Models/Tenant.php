<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use App\Landlord\Subscriptions\Models\FeatureGrant;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\TenantDatabaseConfig;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Landlord\TenantFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string|null $owner_name Null only once the store is purged.
 * @property string|null $owner_email Null only once the store is purged.
 * @property string $country_code ISO 3166-1 alpha-2.
 * @property string $currency_code ISO 4217, the default from the country.
 * @property string $timezone
 * @property string $locale
 * @property CarbonImmutable|null $provisioned_at
 * @property CarbonImmutable|null $suspended_at When a platform admin suspended it.
 * @property string|null $suspension_reason Why, for the platform team; never shown to the store's customers.
 * @property CarbonImmutable|null $closed_at
 * @property string|null $closure_reason
 * @property string|null $closed_by_type The kind of account that closed it: "platform_admin" or "staff_member".
 * @property string|null $closed_by_id That account's public ID.
 * @property TenantStatus|null $status_before_closing Where a restore returns it to, so closing never lifts a suspension.
 * @property CarbonImmutable|null $purge_after When its data may be deleted for good, if it is still closed.
 * @property CarbonImmutable|null $purge_reminder_sent_at
 * @property CarbonImmutable|null $purged_at
 * @property array<string, mixed>|null $data
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Domain> $domains
 * @property-read DatabaseServer|null $databaseServer
 * @property-read Subscription|null $subscription
 * @property-read Collection<int, FeatureGrant> $featureGrants
 * @property-read Collection<int, TenantLimitOverride> $limitOverrides
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
            'suspended_at',
            'suspension_reason',
            'closed_at',
            'closure_reason',
            'closed_by_type',
            'closed_by_id',
            'status_before_closing',
            'purge_after',
            'purge_reminder_sent_at',
            'purged_at',
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
     * The web addresses that open the store. The package's own relation, with its types.
     *
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<DatabaseServer, $this>
     */
    public function databaseServer(): BelongsTo
    {
        return $this->belongsTo(DatabaseServer::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * @return HasMany<FeatureGrant, $this>
     */
    public function featureGrants(): HasMany
    {
        return $this->hasMany(FeatureGrant::class);
    }

    /**
     * @return HasMany<TenantLimitOverride, $this>
     */
    public function limitOverrides(): HasMany
    {
        return $this->hasMany(TenantLimitOverride::class);
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
            'suspended_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'status_before_closing' => TenantStatus::class,
            'purge_after' => 'immutable_datetime',
            'purge_reminder_sent_at' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
