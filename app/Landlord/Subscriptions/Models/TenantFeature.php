<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One store's own record of a module or integration: whether a plan change took it away, and whether the merchant switched it off.
 *
 * When a downgrade removes a feature, the removal is recorded here, which is
 * how the platform knows the feature is locked (existing data read-only)
 * rather than never used. Moving back to a plan that includes it enables it
 * again.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $feature_key
 * @property CarbonImmutable|null $removed_from_plan_at
 * @property CarbonImmutable|null $disabled_by_merchant_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class TenantFeature extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'feature_key',
        'removed_from_plan_at',
        'disabled_by_merchant_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'removed_from_plan_at' => 'immutable_datetime',
            'disabled_by_merchant_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
