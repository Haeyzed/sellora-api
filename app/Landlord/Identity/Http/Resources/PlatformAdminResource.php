<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Resources;

use App\Landlord\Identity\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * A member of Sellora's team as the platform admin app sees them.
 *
 * @property PlatformAdmin $resource
 */
final class PlatformAdminResource extends JsonResource
{
    public function __construct(PlatformAdmin $platformAdmin)
    {
        parent::__construct($platformAdmin);
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
            /** Role names, such as "super_admin". */
            'roles' => array_values($this->resource->getRoleNames()->all()),
            /** Every permission held, directly or through a role, sorted by name. */
            'permissions' => array_values($this->resource->getAllPermissions()->map(static fn (Permission $permission): string => $permission->name)->sort()->all()),
            'last_signed_in_at' => $this->resource->last_signed_in_at?->toIso8601String(),
            /** Whether sign-in asks for a code from an authenticator app. */
            'two_factor_enabled' => $this->resource->hasTwoFactorEnabled(),
        ];
    }
}
