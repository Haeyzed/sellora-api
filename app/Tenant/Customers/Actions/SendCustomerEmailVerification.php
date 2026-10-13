<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Exceptions\CustomerEmailAlreadyVerifiedException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Services\CustomerEmailLinks;

/**
 * Emails a customer a link that proves they receive mail at their account's email. A link sent earlier stops working.
 */
final readonly class SendCustomerEmailVerification
{
    public function __construct(private CustomerEmailLinks $customerEmailLinks) {}

    /**
     * @throws CustomerEmailAlreadyVerifiedException When the email is already confirmed.
     */
    public function handle(Customer $customer): void
    {
        if ($customer->hasVerifiedEmail()) {
            throw new CustomerEmailAlreadyVerifiedException;
        }

        $this->customerEmailLinks->send($customer, CustomerEmailTokenPurpose::Verification, $customer->email);
    }
}
