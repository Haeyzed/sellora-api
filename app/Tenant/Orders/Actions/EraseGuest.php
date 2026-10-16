<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Privacy\GuestDataSubject;

/**
 * Staff erasing a guest's personal data at their request, confirming with the staff member's own password: every feature's guest handler, all or nothing.
 *
 * The activity log records that a guest was erased, never which: their email
 * is exactly what was erased.
 */
final readonly class EraseGuest
{
    public function __construct(
        private PersonalDataRegistry $personalDataRegistry,
        private CurrentPasswordCheck $currentPasswordCheck,
    ) {}

    /**
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function byStaff(StaffMember $actor, string $email, string $actorPassword): void
    {
        $this->currentPasswordCheck->ensureCorrect($actor, $actorPassword);
        $this->personalDataRegistry->erase(GuestDataSubject::of($email));

        activity('orders')->causedBy($actor)->event('guest_erased')->log('Erased the personal data of a guest');
    }
}
