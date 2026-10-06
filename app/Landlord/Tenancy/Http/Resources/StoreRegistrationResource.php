<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Resources;

use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A store sign-up and how far it has got, from waiting for its code to the store being open.
 *
 * @property StoreRegistration $resource
 */
final class StoreRegistrationResource extends JsonResource
{
    public function __construct(StoreRegistration $storeRegistration)
    {
        parent::__construct($storeRegistration);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = $this->resource->tenant;

        return [
            'id' => $this->resource->public_id,
            /** "awaiting_verification", "expired", or once the code is confirmed the store's status: "provisioning", "provisioning_failed" or "active". */
            'status' => $tenant?->status->value ?? ($this->resource->isAwaitingVerification() ? 'awaiting_verification' : 'expired'),
            'store_name' => $this->resource->store_name,
            /** The store's address on the platform domain. */
            'domain' => Tenant::platformDomainFor($this->resource->subdomain),
            /** When the current code stops working. */
            'expires_at' => $this->resource->expires_at->toIso8601String(),
        ];
    }
}
