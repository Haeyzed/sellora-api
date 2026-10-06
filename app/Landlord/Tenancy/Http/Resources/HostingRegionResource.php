<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A region a new store's data can be hosted in.
 *
 * @property string $resource The region code.
 */
final class HostingRegionResource extends JsonResource
{
    public function __construct(string $regionCode)
    {
        parent::__construct($regionCode);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        $name = __('regions.'.$this->resource);

        return [
            /** The code to send when registering, such as "eu". */
            'code' => $this->resource,
            'name' => is_string($name) ? $name : $this->resource,
        ];
    }
}
