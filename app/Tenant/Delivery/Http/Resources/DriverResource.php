<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Tenant\Delivery\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A driver's account as the driver app sees it.
 *
 * @property Driver $resource
 */
final class DriverResource extends JsonResource
{
    public function __construct(Driver $driver)
    {
        parent::__construct($driver);
    }

    /**
     * @return array{id: string, name: string, phone: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            // A signed-in driver is never erased, so both are always set here.
            'name' => (string) $this->resource->name,
            /** In E.164 format. */
            'phone' => (string) $this->resource->phone,
        ];
    }
}
