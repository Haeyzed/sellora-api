<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Services\CustomerEmailLinks;

/**
 * Starts moving a customer's account to a new email: a link is sent to the new address, and nothing changes until the customer uses it while signed in.
 *
 * Needs the current password, so a stolen token alone can't redirect the
 * account's password resets to another mailbox.
 */
final readonly class StartCustomerEmailChange
{
    public function __construct(
        private CurrentPasswordCheck $currentPasswordCheck,
        private CustomerEmailLinks $customerEmailLinks,
    ) {}

    /**
     * @param  string  $newEmail  Already trimmed and lower-cased, and not used by another account.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function handle(Customer $customer, string $currentPassword, string $newEmail): void
    {
        $this->currentPasswordCheck->ensureCorrect($customer, $currentPassword);

        $this->customerEmailLinks->send($customer, CustomerEmailTokenPurpose::EmailChange, $newEmail);
    }
}
