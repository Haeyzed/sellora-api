<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer account as staff see it.
 *
 * @property Customer $resource
 */
final class StaffCustomerResource extends JsonResource
{
    public function __construct(Customer $customer)
    {
        parent::__construct($customer);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customer = $this->resource;

        return [
            'id' => $customer->public_id,
            'name' => $customer->name,
            'email' => $customer->email,
            'email_verified' => $customer->hasVerifiedEmail(),
            /** In E.164 format. */
            'phone' => $customer->phone,
            /** The language emails to the customer are written in; null for the store's default. */
            'locale' => $customer->locale,
            /** The tax ID the customer entered; only its format was checked. */
            'tax_id' => $customer->tax_id,
            'tax_id_country' => $customer->tax_id_country,
            'is_tax_exempt' => $customer->is_tax_exempt,
            /** False when staff deactivated the account: the customer can't sign in. */
            'is_active' => $customer->is_active,
            'last_signed_in_at' => $customer->last_signed_in_at?->toIso8601String(),
            'created_at' => $customer->created_at?->toIso8601String(),
            /** When it was moved to the trash; null when it isn't there. */
            'deleted_at' => $customer->deleted_at?->toIso8601String(),
        ];
    }
}
