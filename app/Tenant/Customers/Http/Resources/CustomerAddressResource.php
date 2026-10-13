<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of a customer's saved addresses.
 *
 * @property CustomerAddress $resource
 */
final class CustomerAddressResource extends JsonResource
{
    /**
     * @param  Customer  $customer  Whose address it is, to say whether it is one of their defaults.
     */
    public function __construct(CustomerAddress $address, private readonly Customer $customer)
    {
        parent::__construct($address);
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function toArray(Request $request): array
    {
        $address = $this->resource;

        return [
            'id' => $address->public_id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'company' => $address->company,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            /** The state's code within the country, such as "LA". */
            'state' => $address->state_code,
            'postal_code' => $address->postal_code,
            /** ISO 3166-1 alpha-2. */
            'country' => $address->country_code,
            /** In E.164 format. */
            'phone' => $address->phone,
            'is_default_shipping' => $this->customer->default_shipping_address_id === $address->id,
            'is_default_billing' => $this->customer->default_billing_address_id === $address->id,
        ];
    }
}
