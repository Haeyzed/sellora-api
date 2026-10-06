<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Resources;

use App\Landlord\Identity\Models\PlatformAdminInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invitation to Sellora's team, as super admins see it. Never includes the link.
 *
 * @property PlatformAdminInvitation $resource
 */
final class PlatformAdminInvitationResource extends JsonResource
{
    public function __construct(PlatformAdminInvitation $platformAdminInvitation)
    {
        parent::__construct($platformAdminInvitation);
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
            'roles' => PlatformRoleSummaryResource::collection($this->whenLoaded('roles')),
            /** "pending", or "expired" when the link no longer works and can be resent. */
            'status' => $this->resource->isPending() ? 'pending' : 'expired',
            'expires_at' => $this->resource->expires_at->toIso8601String(),
            /** Who sent it, if their account still exists. */
            'invited_by' => $this->whenLoaded('invitedBy', fn (): ?array => $this->resource->invitedBy === null ? null : [
                'id' => $this->resource->invitedBy->public_id,
                'name' => $this->resource->invitedBy->name,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
