<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A usage limit set for one store instead of its plan's, such as 1,000 products for a launch, optionally until a date.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $limit_key One of the keys in config/features.php.
 * @property int|null $limit_value Null only when unlimited.
 * @property bool $is_unlimited Set on purpose; a missing value never means unlimited.
 * @property CarbonImmutable|null $expires_at After this, the plan's limit applies again.
 * @property string|null $reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class TenantLimitOverride extends Model implements AuditableContract
{
    use Auditable;
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'limit_key',
        'limit_value',
        'is_unlimited',
        'expires_at',
        'reason',
    ];

    /**
     * @var list<string>
     */
    protected $auditInclude = ['limit_key', 'limit_value', 'is_unlimited', 'expires_at', 'reason'];

    /**
     * Whether it applies now; after its date the plan's limit applies again.
     */
    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * The limit this override sets: null only when it is unlimited on purpose. A value missing without the unlimited flag (which the database refuses) counts as zero, never unlimited.
     */
    public function effectiveLimit(): ?int
    {
        if ($this->is_unlimited) {
            return null;
        }

        return $this->limit_value ?? 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
            'is_unlimited' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
