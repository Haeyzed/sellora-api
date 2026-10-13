<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Exceptions\CustomerTaxIdMissingException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Services\CustomerActivity;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Staff make a customer tax-exempt, after checking the tax ID the customer entered, or end the exemption. Customers can never make themselves exempt.
 *
 * Audited, and written in the activity log.
 */
final readonly class ChangeCustomerTaxExemption
{
    public function __construct(private CustomerActivity $customerActivity) {}

    /**
     * @throws CustomerTaxIdMissingException When granting an exemption to a customer without a tax ID.
     */
    public function handle(StaffMember $actor, Customer $customer, bool $exempt): Customer
    {
        return DB::transaction(function () use ($actor, $customer, $exempt): Customer {
            $locked = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);

            if ($exempt && $locked->tax_id === null) {
                throw new CustomerTaxIdMissingException;
            }

            if ($locked->is_tax_exempt !== $exempt) {
                $locked->forceFill(['is_tax_exempt' => $exempt])->save();
                $this->customerActivity->record($actor, $locked, $exempt ? 'customer_tax_exemption_granted' : 'customer_tax_exemption_ended', $exempt ? 'Made tax-exempt' : 'Ended the tax exemption of');
            }

            return $locked;
        });
    }
}
