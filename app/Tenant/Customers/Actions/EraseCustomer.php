<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Privacy\ErasureOutcome;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Customers\Services\CustomerActivity;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Erases a customer's personal data from every feature, all or nothing (section 3.3): on their own request, at staff's on their behalf, or once they have been in the trash for the retention period.
 *
 * The account row stays, with every personal field cleared, so past orders
 * still point at something; files go only after the erasure commits.
 */
final readonly class EraseCustomer
{
    public function __construct(
        private PersonalDataRegistry $personalDataRegistry,
        private CurrentPasswordCheck $currentPasswordCheck,
        private CustomerActivity $customerActivity,
    ) {}

    /**
     * The customer erasing their own account, with their current password.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function bySelf(Customer $customer, string $currentPassword): ErasureOutcome
    {
        $this->currentPasswordCheck->ensureCorrect($customer, $currentPassword);

        return $this->erase($customer);
    }

    /**
     * Staff erasing a customer at their request, confirming with the staff member's own password.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function byStaff(StaffMember $actor, Customer $customer, string $actorPassword): ErasureOutcome
    {
        $this->currentPasswordCheck->ensureCorrect($actor, $actorPassword);
        $outcome = $this->erase($customer);
        $this->customerActivity->record($actor, $customer, 'customer_erased', 'Erased the personal data of');

        return $outcome;
    }

    /**
     * Erases without asking anyone, for the retention purge of customers long in the trash.
     *
     * @return ErasureOutcome What the law makes the store keep, such as tax invoices, and until when.
     */
    public function erase(Customer $customer): ErasureOutcome
    {
        $subject = CustomerDataSubject::of($customer);
        $keptUntil = $this->personalDataRegistry->keptByLawUntil($subject);
        $this->personalDataRegistry->erase($subject);

        return new ErasureOutcome($keptUntil);
    }
}
