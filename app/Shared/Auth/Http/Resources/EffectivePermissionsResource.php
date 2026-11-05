<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Resources;

use App\Shared\Auth\Contracts\GrantablePermission;
use App\Shared\Auth\Contracts\GrantablePermissions;
use App\Shared\Auth\EffectivePermissions;
use App\Shared\Auth\PermissionTexts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything a person may do, and why: each permission with the roles that give it and whether it was also given directly.
 *
 * A permission still held but defined by no code (from removed code, kept
 * by the sync until no role or person holds it) is left out: it allows
 * nothing, and it has no label to show.
 *
 * @property EffectivePermissions $resource
 */
final class EffectivePermissionsResource extends JsonResource
{
    public function __construct(
        EffectivePermissions $effectivePermissions,
        private readonly GrantablePermissions $grantablePermissions,
        private readonly PermissionTexts $permissionTexts,
    ) {
        parent::__construct($effectivePermissions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permissions = [];

        foreach ($this->resource->permissions as $held) {
            $permission = $this->grantablePermissions->find($held['name']);

            if ($permission instanceof GrantablePermission) {
                $permissions[] = [...(new GrantablePermissionResource($this->permissionTexts->describe($permission)))->toArray($request), 'sources' => $held['sources']];
            }
        }

        return [
            /** True for a store's owner or a platform super admin, who may do everything; every permission then comes from that role. */
            'holds_every_permission' => $this->resource->holdsEveryPermission,
            /**
             * Sorted by name. "sources" lists where it comes from: {"type": "role", "role": "Cashier"} or {"type": "direct", "role": null}.
             *
             * @var list<array{name: string, label: string, description: string, group: array{key: string, label: string}, is_sensitive: bool, sources: list<array{type: 'role'|'direct', role: string|null}>}>
             */
            'permissions' => $permissions,
        ];
    }
}
