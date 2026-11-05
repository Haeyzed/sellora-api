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
 * A permission still held but defined by no code (a leftover grant from
 * removed code, kept by the sync until no role or person holds it) is shown,
 * not hidden, because the permissions package still lets it through: it is
 * marked unknown, labelled with its key, and treated as sensitive, so
 * dashboards can ask for it to be removed.
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

            $permissions[] = $permission instanceof GrantablePermission
                ? [...$this->known($permission, $request), 'is_unknown' => false, 'sources' => $held['sources']]
                : [...$this->unknown($held['name']), 'is_unknown' => true, 'sources' => $held['sources']];
        }

        return [
            /** True for a store's owner or a platform super admin, who may do everything; every permission then comes from that role. */
            'holds_every_permission' => $this->resource->holdsEveryPermission,
            /**
             * Sorted by name. "sources" lists where it comes from: {"type": "role", "role": "Cashier"} or {"type": "direct", "role": null}.
             * "is_unknown" is true for a leftover grant: a permission no code defines any more that still lets its holder through. Its label
             * is its key, its description and group are null and it counts as sensitive; it should be removed from the role or person.
             *
             * @var list<array{name: string, label: string, description: string|null, group: array{key: string, label: string}|null, is_sensitive: bool, is_unknown: bool, sources: list<array{type: 'role'|'direct', role: string|null}>}>
             */
            'permissions' => $permissions,
        ];
    }

    /**
     * @return array{name: string, label: string, description: string, group: array{key: string, label: string}, is_sensitive: bool}
     */
    private function known(GrantablePermission $permission, Request $request): array
    {
        return (new GrantablePermissionResource($this->permissionTexts->describe($permission)))->toArray($request);
    }

    /**
     * A leftover grant has no texts, so its key stands in for its label; it is sensitive because nobody can say what it allows.
     *
     * @return array{name: string, label: string, description: null, group: null, is_sensitive: true}
     */
    private function unknown(string $name): array
    {
        return ['name' => $name, 'label' => $name, 'description' => null, 'group' => null, 'is_sensitive' => true];
    }
}
