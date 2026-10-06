<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Resources;

use App\Landlord\Subscriptions\Models\FeatureGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A module or integration given to a store outside its plan.
 *
 * @property FeatureGrant $resource
 */
final class FeatureGrantResource extends JsonResource
{
    public function __construct(FeatureGrant $featureGrant)
    {
        parent::__construct($featureGrant);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'feature' => $this->resource->feature_key,
            'reason' => $this->resource->reason,
            /** "active", or "ended" once its date passed or it was revoked; an ended grant leaves the feature locked unless the plan includes it. */
            'status' => $this->resource->isActive() ? 'active' : 'ended',
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
            'revoked_at' => $this->resource->revoked_at?->toIso8601String(),
            'granted_by' => $this->whenLoaded('grantedBy', fn (): ?string => $this->resource->grantedBy?->name),
            'revoked_by' => $this->whenLoaded('revokedBy', fn (): ?string => $this->resource->revokedBy?->name),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
