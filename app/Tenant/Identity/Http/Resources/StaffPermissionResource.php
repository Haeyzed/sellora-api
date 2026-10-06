<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A permission roles can grant, with a description in the request's language.
 *
 * @property string $resource
 */
final class StaffPermissionResource extends JsonResource
{
    public function __construct(string $permissionName)
    {
        parent::__construct($permissionName);
    }

    /**
     * @return array{name: string, description: string}
     */
    public function toArray(Request $request): array
    {
        $description = __('permissions.'.$this->resource);

        return [
            'name' => $this->resource,
            'description' => is_string($description) ? $description : $this->resource,
        ];
    }
}
