<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member of the store's team, as colleagues managing the team see them.
 *
 * @property StaffMember $resource
 */
final class TeamMemberResource extends JsonResource
{
    public function __construct(StaffMember $staffMember)
    {
        parent::__construct($staffMember);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            /** False once deactivated: they can't sign in, and don't count towards the staff limit. */
            'is_active' => $this->resource->is_active,
            'roles' => RoleSummaryResource::collection($this->whenLoaded('roles')),
            'two_factor_enabled' => $this->resource->hasTwoFactorEnabled(),
            'last_signed_in_at' => $this->resource->last_signed_in_at?->toIso8601String(),
            'joined_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
