<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Resources;

use App\Shared\Auth\EffectivePermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything a person may do, and why: each permission with the roles that give it and whether it was also given directly.
 *
 * @property EffectivePermissions $resource
 */
final class EffectivePermissionsResource extends JsonResource
{
    public function __construct(EffectivePermissions $effectivePermissions)
    {
        parent::__construct($effectivePermissions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** True for a store's owner or a platform super admin, who may do everything; every permission then comes from that role. */
            'holds_every_permission' => $this->resource->holdsEveryPermission,
            /**
             * Sorted by name. "sources" lists where it comes from: {"type": "role", "role": "Cashier"} or {"type": "direct", "role": null}.
             *
             * @var list<array{name: string, description: string, sources: list<array{type: 'role'|'direct', role: string|null}>}>
             */
            'permissions' => array_map(static function (array $permission): array {
                $description = __('permissions.'.$permission['name']);

                return [
                    'name' => $permission['name'],
                    'description' => is_string($description) ? $description : $permission['name'],
                    'sources' => $permission['sources'],
                ];
            }, $this->resource->permissions),
        ];
    }
}
