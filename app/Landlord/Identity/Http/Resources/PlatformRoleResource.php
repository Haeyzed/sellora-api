<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Resources;

use App\Landlord\Identity\Enums\PlatformRole;
use App\Shared\Auth\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * A platform role and what it grants.
 *
 * @property Role $resource
 */
final class PlatformRoleResource extends JsonResource
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
        $isSuperAdmin = $this->resource->name === PlatformRole::SuperAdmin->value;

        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            /** Built-in roles can't be renamed, changed or deleted. */
            'is_built_in' => $isSuperAdmin,
            /** True for Super Admin, which may do everything without needing each permission. */
            'grants_everything' => $isSuperAdmin,
            /** @var list<string> Permission names, sorted. Empty for Super Admin, which needs none. */
            'permissions' => $this->whenLoaded('permissions', fn (): array => array_values($this->resource->permissions->map(static fn (Permission $permission): string => $permission->name)->sort()->all())),
            /** How many platform admins have the role, deactivated ones included. */
            'admin_count' => $this->whenHas('admin_count'),
        ];
    }
}
