<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Resources;

use App\Landlord\Identity\Enums\PlatformPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A permission platform roles can grant, with a description in the request's language.
 *
 * @property PlatformPermission $resource
 */
final class PlatformPermissionResource extends JsonResource
{
    public function __construct(PlatformPermission $platformPermission)
    {
        parent::__construct($platformPermission);
    }

    /**
     * @return array{name: string, description: string}
     */
    public function toArray(Request $request): array
    {
        $description = __('permissions.'.$this->resource->value);

        return [
            'name' => $this->resource->value,
            'description' => is_string($description) ? $description : $this->resource->value,
        ];
    }
}
