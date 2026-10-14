<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use App\Tenant\Identity\Data\StaffPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A permission roles can grant, with a description in the request's language.
 *
 * @property StaffPermission $resource
 */
final class StaffPermissionResource extends JsonResource
{
    public function __construct(string $permissionName)
    {
        parent::__construct(new StaffPermission($permissionName));
    }

    /**
     * @return array{name: string, description: string}
     */
    public function toArray(Request $request): array
    {
        $name = $this->resource->name;
        $description = __('permissions.'.$name);

        return [
            'name' => $name,
            'description' => is_string($description) ? $description : $name,
        ];
    }
}
