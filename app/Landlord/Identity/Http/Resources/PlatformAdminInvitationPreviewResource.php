<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Resources;

use App\Landlord\Identity\Models\PlatformAdminInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the person invited sees before accepting: who invited them, and with which roles.
 *
 * @property PlatformAdminInvitation $resource
 */
final class PlatformAdminInvitationPreviewResource extends JsonResource
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
            'email' => $this->resource->email,
            /** Suggested name, which they can change. */
            'name' => $this->resource->name,
            'roles' => PlatformRoleSummaryResource::collection($this->whenLoaded('roles')),
            'invited_by' => $this->resource->invitedBy?->name,
            'expires_at' => $this->resource->expires_at->toIso8601String(),
        ];
    }
}
