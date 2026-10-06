<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Resources;

use App\Landlord\Identity\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member of Sellora's team as super admins managing the team see them.
 *
 * @property PlatformAdmin $resource
 */
final class PlatformTeamMemberResource extends JsonResource
{
    public function __construct(PlatformAdmin $platformAdmin)
    {
        parent::__construct($platformAdmin);
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
            'is_active' => $this->resource->is_active,
            'roles' => PlatformRoleSummaryResource::collection($this->whenLoaded('roles')),
            /** Whether they have set up two-factor authentication. Until they do, they can't use the platform. */
            'two_factor_enabled' => $this->resource->hasTwoFactorEnabled(),
            'last_signed_in_at' => $this->resource->last_signed_in_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
