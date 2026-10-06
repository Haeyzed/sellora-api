<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Resources;

use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A usage limit set for one store instead of its plan's.
 *
 * @property TenantLimitOverride $resource
 */
final class LimitOverrideResource extends JsonResource
{
    public function __construct(TenantLimitOverride $limitOverride)
    {
        parent::__construct($limitOverride);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** The limit, such as "products". */
            'limit' => $this->resource->limit_key,
            /** How many the store may have; null only when unlimited is true. */
            'value' => $this->resource->is_unlimited ? null : $this->resource->limit_value,
            /** True when the limit was removed for this store on purpose. */
            'unlimited' => $this->resource->is_unlimited,
            'reason' => $this->resource->reason,
            /** After this the plan's limit applies again; null for no end date. */
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
            /** Whether it applies now; false once its date has passed. */
            'is_active' => $this->resource->isActive(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
