<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Tenant\Delivery\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A driver as staff manage them. The PIN itself never leaves the store; staff only see whether one has been chosen.
 *
 * @property Driver $resource
 */
final class DriverAccountResource extends JsonResource
{
    public function __construct(Driver $driver)
    {
        parent::__construct($driver);
    }

    /**
     * @return array{id: string, name: string|null, phone: string|null, is_active: bool, has_pin: bool, is_erased: bool, last_signed_in_at: string|null, created_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            /** Null once the driver's personal data has been erased. */
            'name' => $this->resource->name,
            /** In E.164 format. Null once erased. */
            'phone' => $this->resource->phone,
            'is_active' => $this->resource->is_active,
            /** Whether the driver has chosen a PIN; false until they use a setup code. */
            'has_pin' => $this->resource->pin !== null,
            'is_erased' => $this->resource->isErased(),
            'last_signed_in_at' => $this->resource->last_signed_in_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
