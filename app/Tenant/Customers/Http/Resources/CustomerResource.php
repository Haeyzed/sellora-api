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
     * Never an erased account: erasure signs the customer out everywhere, and only staff still see the cleared row.
     *
     * @return array{id: string, name: string|null, email: string|null, email_verified: bool, phone: string|null, locale: string|null, tax_id: string|null, tax_id_country: string|null, is_tax_exempt: bool}
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
            /** For buying as a business. */
            'tax_id' => $this->resource->tax_id,
            'tax_id_country' => $this->resource->tax_id_country,
            /** Whether the store made the customer tax-exempt; only staff change it. */
            'is_tax_exempt' => $this->resource->is_tax_exempt,
        ];
    }
}
