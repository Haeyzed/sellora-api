<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer group, for staff.
 *
 * @property CustomerGroup $resource
 */
final class CustomerGroupResource extends JsonResource
{
    public function __construct(CustomerGroup $group)
    {
        parent::__construct($group);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            /** For staff only. */
            'description' => $this->resource->description,
            /** How many customers are in it, including any in the trash. */
            'members_count' => $this->resource->members_count ?? $this->resource->members()->count(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
