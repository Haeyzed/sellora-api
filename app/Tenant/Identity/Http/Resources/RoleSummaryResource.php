<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Resources;

use App\Shared\Auth\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A role as shown on a team member or an invitation: its ID, to change roles with, and its name.
 *
 * @property Role $resource
 */
final class RoleSummaryResource extends JsonResource
{
    public function __construct(Role $role)
    {
        parent::__construct($role);
    }

    /**
     * @return array{id: string, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
        ];
    }
}
