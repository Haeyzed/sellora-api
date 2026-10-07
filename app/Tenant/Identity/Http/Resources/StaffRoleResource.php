<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * One of the store's roles and what it grants.
 *
 * @property Role $resource
 */
final class StaffRoleResource extends JsonResource
{
    public function __construct(Role $role)
    {
        parent::__construct($role);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwner = $this->resource->name === StaffRole::Owner->value;

        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            /** Built-in roles can't be renamed, changed or deleted. */
            'is_built_in' => $isOwner,
            /** True for Owner, which may do everything without needing each permission. */
            'grants_everything' => $isOwner,
            /** @var list<string> Permission names, sorted. Empty for Owner, which needs none. */
            'permissions' => $this->whenLoaded('permissions', fn (): array => array_values($this->resource->permissions->map(static fn (Permission $permission): string => $permission->name)->sort()->all())),
            /** How many staff members have the role, deactivated ones included. */
            'staff_count' => $this->whenHas('staff_count'),
        ];
    }
}
