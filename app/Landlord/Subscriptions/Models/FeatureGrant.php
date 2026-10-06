<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Models;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A module or integration given to one store by a platform admin, outside its plan, such as "Loyalty free for 30 days".
 *
 * While active, the store has the feature as if its plan included it. Once it
 * ends (its date passes, or it is revoked) the feature is locked like after a
 * downgrade: what the store created stays readable, nothing new can be added.
 *
 * @property int $id
 * @property string $public_id
 * @property string $tenant_id
 * @property string $feature_key
 * @property string $reason
 * @property int|null $granted_by_id
 * @property CarbonImmutable|null $expires_at Null when it has no end date.
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $revoked_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tenant $tenant
 * @property-read PlatformAdmin|null $grantedBy
 * @property-read PlatformAdmin|null $revokedBy
 */
final class FeatureGrant extends Model implements AuditableContract
{
    use Auditable;
    use CentralConnection;
    use HasPublicId;

    protected $fillable = [
        'tenant_id',
        'feature_key',
        'reason',
        'expires_at',
    ];

    /**
     * @var list<string>
     */
    protected $auditInclude = ['feature_key', 'reason', 'expires_at', 'revoked_at'];

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'granted_by_id');
    }

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'revoked_by_id');
    }

    /**
     * Grants that currently give the store the feature.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where(static function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
