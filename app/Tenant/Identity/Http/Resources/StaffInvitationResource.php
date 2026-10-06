<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use App\Tenant\Identity\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invitation to the store's team, as colleagues managing the team see it. Never includes the link.
 *
 * @property StaffInvitation $resource
 */
final class StaffInvitationResource extends JsonResource
{
    public function __construct(StaffInvitation $staffInvitation)
    {
        parent::__construct($staffInvitation);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'email' => $this->resource->email,
            'name' => $this->resource->name,
            'roles' => RoleSummaryResource::collection($this->whenLoaded('roles')),
            /** "pending", or "expired" when the link no longer works and can be resent. */
            'status' => $this->resource->isPending() ? 'pending' : 'expired',
            'expires_at' => $this->resource->expires_at->toIso8601String(),
            /** Who sent it, if they are still on the team. */
            'invited_by' => $this->whenLoaded('invitedBy', fn (): ?array => $this->resource->invitedBy === null ? null : [
                'id' => $this->resource->invitedBy->public_id,
                'name' => $this->resource->invitedBy->name,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
