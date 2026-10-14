<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Exceptions\CustomerEmailAlreadyVerifiedException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Services\CustomerEmailLinks;
use LogicException;

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

        // An erased customer has no email, and no sign-in to ask with.
        $email = $customer->email ?? throw new LogicException('An erased customer has no email to verify.');

        $this->customerEmailLinks->send($customer, CustomerEmailTokenPurpose::Verification, $email);
    }
}
