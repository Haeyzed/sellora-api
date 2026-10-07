<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the person opening an invitation link sees before accepting: who it is for and what they will be.
 *
 * @property StaffInvitation $resource
 */
final class StaffInvitationPreviewResource extends JsonResource
{
    public function __construct(StaffInvitation $staffInvitation)
    {
        parent::__construct($staffInvitation);
    }

    /**
     * @return array{email: string, name: string|null, roles: list<string>, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->resource->email,
            /** The name the inviter suggested, to prefill. */
            'name' => $this->resource->name,
            /** @var list<string> Role names the person will have. */
            'roles' => array_values($this->resource->roles->map(static fn (Role $role): string => $role->name)->all()),
            'expires_at' => $this->resource->expires_at->toIso8601String(),
        ];
    }
}
