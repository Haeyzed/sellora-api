<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Resources;

use App\Tenant\Promotions\Models\PromotionCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of a promotion's codes, for staff.
 *
 * @property PromotionCode $resource
 */
final class PromotionCodeResource extends JsonResource
{
    public function __construct(PromotionCode $code)
    {
        parent::__construct($code);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            /** As staff wrote it; customers may type it in any case. */
            'code' => $this->resource->code,
            'usage_limit' => $this->resource->usage_limit,
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
