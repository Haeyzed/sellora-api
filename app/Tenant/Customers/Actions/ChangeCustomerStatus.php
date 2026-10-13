<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Services\CustomerActivity;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Staff deactivate, reactivate, trash or restore a customer account.
 *
 * Deactivating and trashing sign the customer out everywhere at once. A
 * deactivated customer can't sign in until reactivated; a trashed one is
 * erased for good once it has been in the trash for the retention period
 * (Step 10f), unless restored before then. Each change is in the activity log.
 */
final readonly class ChangeCustomerStatus
{
    public function __construct(
        private AccessTokenIssuer $accessTokenIssuer,
        private CustomerActivity $customerActivity,
    ) {}

    public function deactivate(StaffMember $actor, Customer $customer): Customer
    {
        if (! $customer->is_active) {
            return $customer;
        }

        return DB::transaction(function () use ($actor, $customer): Customer {
            $customer->forceFill(['is_active' => false])->save();
            $this->accessTokenIssuer->revokeAll($customer);
            $this->customerActivity->record($actor, $customer, 'customer_deactivated', 'Deactivated');

            return $customer;
        });
    }

    public function reactivate(StaffMember $actor, Customer $customer): Customer
    {
        if ($customer->is_active) {
            return $customer;
        }

        return DB::transaction(function () use ($actor, $customer): Customer {
            $customer->forceFill(['is_active' => true])->save();
            $this->customerActivity->record($actor, $customer, 'customer_reactivated', 'Reactivated');

            return $customer;
        });
    }

    public function trash(StaffMember $actor, Customer $customer): void
    {
        if ($customer->trashed()) {
            return;
        }

        DB::transaction(function () use ($actor, $customer): void {
            $customer->delete();
            $this->accessTokenIssuer->revokeAll($customer);
            $this->customerActivity->record($actor, $customer, 'customer_trashed', 'Moved to the trash');
        });
    }

    public function restore(StaffMember $actor, Customer $customer): Customer
    {
        if (! $customer->trashed()) {
            return $customer;
        }

        return DB::transaction(function () use ($actor, $customer): Customer {
            $customer->restore();
            $this->customerActivity->record($actor, $customer, 'customer_restored', 'Restored');

            return $customer;
        });
    }
}
