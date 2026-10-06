<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A usage limit set for one store instead of its plan's, such as 1,000 products for a launch, optionally until a date.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $limit_key One of the keys in config/features.php.
 * @property int|null $limit_value Null means unlimited.
 * @property CarbonImmutable|null $expires_at After this, the plan's limit applies again.
 * @property string|null $reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class TenantLimitOverride extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'limit_key',
        'limit_value',
        'expires_at',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
