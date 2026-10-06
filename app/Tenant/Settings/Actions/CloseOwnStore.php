<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\IdentityConfirmation;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\ClosedStore;
use App\Shared\Tenancy\Contracts\StoreClosure;
use App\Tenant\Identity\Models\StaffMember;

/**
 * The owner closes their own store. It stops opening for everyone at once, and its data is deleted for good after the purge date.
 *
 * Needs the current password, plus a code when the owner uses two-factor
 * authentication. The closing is recorded in the store's own activity log
 * first, so it is there if the store is ever restored.
 */
final readonly class CloseOwnStore
{
    public function __construct(
        private IdentityConfirmation $identityConfirmation,
        private StoreClosure $storeClosure,
    ) {}

    /**
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws InvalidTwoFactorCodeException When the owner uses two-factor authentication and the code is missing, wrong or used.
     * @throws TooManyIncorrectAttemptsException After too many wrong passwords or codes.
     * @throws DomainException When the store can't be closed in its current status.
     */
    public function handle(StaffMember $owner, string $currentPassword, ?string $code, ?string $recoveryCode, ?string $reason): ClosedStore
    {
        $this->identityConfirmation->ensureConfirmed($owner, $currentPassword, $code, $recoveryCode);

        activity('store')
            ->causedBy($owner)
            ->event('store_closed')
            ->withProperties(['reason' => $reason])
            ->log("{$owner->name} closed the store");

        return $this->storeClosure->closeCurrentStore(AccountReference::to($owner), $reason);
    }
}
