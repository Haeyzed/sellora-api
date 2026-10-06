<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * A staff member as the store dashboard sees them.
 *
 * @property StaffMember $resource
 */
final class StaffMemberResource extends JsonResource
{
    public function __construct(StaffMember $staffMember)
    {
        parent::__construct($staffMember);
    }

    /**
     * @return array{id: string, name: string, email: string, roles: list<string>, permissions: list<string>, last_signed_in_at: string|null, two_factor_enabled: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            /** Role names in this store, such as "owner". */
            'roles' => array_values($this->resource->getRoleNames()->all()),
            /** Every permission held in this store, directly or through a role, sorted by name. */
            'permissions' => array_values($this->resource->getAllPermissions()->map(static fn (Permission $permission): string => $permission->name)->sort()->all()),
            'last_signed_in_at' => $this->resource->last_signed_in_at?->toIso8601String(),
            /** Whether sign-in asks for a code from an authenticator app. */
            'two_factor_enabled' => $this->resource->hasTwoFactorEnabled(),
        ];
    }
}
