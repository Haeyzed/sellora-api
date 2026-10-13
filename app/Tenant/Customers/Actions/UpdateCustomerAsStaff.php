<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Services\CustomerActivity;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Staff correct a customer's name, phone or language, for example at the customer's request.
 *
 * Never the email: that would let staff redirect the customer's password
 * resets to another mailbox. The customer changes it through an emailed link.
 */
final readonly class UpdateCustomerAsStaff
{
    public function __construct(private CustomerActivity $customerActivity) {}

    /**
     * @param  array{name?: string, phone?: string|null, locale?: string|null}  $changes  Validated; a phone in E.164.
     */
    public function handle(StaffMember $actor, Customer $customer, array $changes): Customer
    {
        return DB::transaction(function () use ($actor, $customer, $changes): Customer {
            $customer->fill($changes);
            $changed = array_values(array_intersect(array_keys($customer->getDirty()), ['name', 'phone', 'locale']));

            if ($changed !== []) {
                $customer->save();
                $this->customerActivity->record($actor, $customer, 'customer_updated', 'Updated', $changed);
            }

            return $customer;
        });
    }
}
