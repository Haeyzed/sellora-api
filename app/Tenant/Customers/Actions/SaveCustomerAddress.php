<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Data\AddressDetails;
use App\Tenant\Customers\Exceptions\CustomerAddressLimitReachedException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new address for a customer, or replaces one of theirs, and sets it as their default for delivery or billing when asked.
 *
 * A customer has at most config('customers.max_addresses') addresses,
 * counted with their row locked, so two requests at once can't both pass.
 */
final readonly class SaveCustomerAddress
{
    /**
     * @param  CustomerAddress|null  $address  One of the customer's addresses to replace, or null for a new one.
     *
     * @throws CustomerAddressLimitReachedException When a new address would pass the limit.
     */
    public function handle(Customer $customer, AddressDetails $details, ?CustomerAddress $address = null): CustomerAddress
    {
        return DB::transaction(static function () use ($customer, $details, $address): CustomerAddress {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($address === null) {
                $limit = config()->integer('customers.max_addresses');

                if ($locked->addresses()->count() >= $limit) {
                    throw new CustomerAddressLimitReachedException($limit);
                }

                $address = $locked->addresses()->make();
            }

            $address->fill($details->fields)->save();

            $defaults = [];

            foreach (['shipping' => $details->defaultShipping, 'billing' => $details->defaultBilling] as $kind => $makeDefault) {
                $column = "default_{$kind}_address_id";

                if ($makeDefault === true) {
                    $defaults[$column] = $address->id;
                } elseif ($makeDefault === false && $locked->{$column} === $address->id) {
                    $defaults[$column] = null;
                }
            }

            if ($defaults !== []) {
                $locked->forceFill($defaults)->save();
                $customer->forceFill($defaults)->syncOriginalAttributes(array_keys($defaults));
            }

            return $address;
        });
    }
}
