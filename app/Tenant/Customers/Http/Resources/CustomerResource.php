<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shopper's account as the storefront sees it.
 *
 * @property Customer $resource
 */
final class CustomerResource extends JsonResource
{
    public function __construct(Customer $customer)
    {
        parent::__construct($customer);
    }

    /**
     * @return array{id: string, name: string, email: string, email_verified: bool, phone: string|null, locale: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            /** Whether the customer confirmed they receive mail at this email. Unverified accounts can't link guest orders or download their data. */
            'email_verified' => $this->resource->hasVerifiedEmail(),
            /** In E.164 format. */
            'phone' => $this->resource->phone,
            /** The language emails to the customer are written in; null for the store's default. */
            'locale' => $this->resource->locale,
        ];
    }
}
