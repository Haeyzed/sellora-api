<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Resources;

use App\Shared\Auth\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A platform role as shown next to an admin or invitation: its ID and name.
 *
 * @property Role $resource
 */
final class PlatformRoleSummaryResource extends JsonResource
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
