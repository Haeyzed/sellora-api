<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Tenant\Delivery\Data\IssuedDriverSetupCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A PIN setup code just issued, shown this once: give it to the driver, who chooses their own PIN with it.
 *
 * @property IssuedDriverSetupCode $resource
 */
final class IssuedDriverSetupCodeResource extends JsonResource
{
    public function __construct(IssuedDriverSetupCode $issuedDriverSetupCode)
    {
        parent::__construct($issuedDriverSetupCode);
    }

    /**
     * @return array{driver: DriverAccountResource, setup_code: string, expires_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'driver' => new DriverAccountResource($this->resource->driver),
            /** 8 digits. Shown only now; only a hash is kept. */
            'setup_code' => $this->resource->code,
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
        ];
    }
}
