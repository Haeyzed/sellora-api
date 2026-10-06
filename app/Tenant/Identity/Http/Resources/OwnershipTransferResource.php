<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use App\Tenant\Identity\Models\OwnershipTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An offer to hand the store to a colleague, as the owner and that colleague see it.
 *
 * @property OwnershipTransfer $resource
 */
final class OwnershipTransferResource extends JsonResource
{
    public function __construct(OwnershipTransfer $ownershipTransfer)
    {
        parent::__construct($ownershipTransfer);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            /** A pending transfer past its 72 hours shows as expired. */
            'status' => $this->resource->currentStatus(),
            /** The owner who offered the store. */
            'from' => $this->whenLoaded('fromStaffMember', fn (): array => [
                'id' => $this->resource->fromStaffMember->public_id,
                'name' => $this->resource->fromStaffMember->name,
            ]),
            /** The colleague who becomes the owner once they accept. */
            'to' => $this->whenLoaded('toStaffMember', fn (): array => [
                'id' => $this->resource->toStaffMember->public_id,
                'name' => $this->resource->toStaffMember->name,
            ]),
            /** The roles the current owner keeps afterwards; may be empty. */
            'kept_roles' => RoleSummaryResource::collection($this->whenLoaded('keptRoles')),
            'expires_at' => $this->resource->expires_at->toIso8601String(),
            'accepted_at' => $this->resource->accepted_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
