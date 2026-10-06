<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\Models\Role;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use App\Tenant\Identity\Enums\OwnershipTransferStatus;
use App\Tenant\Identity\Exceptions\InvalidOwnershipTransferRecipientException;
use App\Tenant\Identity\Exceptions\OwnershipTransferAlreadyPendingException;
use App\Tenant\Identity\Exceptions\RoleNotAssignableException;
use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\OwnershipTransferOfferNotification;
use App\Tenant\Identity\Services\StaffAuthority;
use Carbon\CarbonImmutable;

/**
 * The owner offers the store to a colleague. Nothing changes until that colleague accepts.
 *
 * Handing over a store is the most powerful thing an owner can do, so it
 * needs the current password, plus a code when the owner uses two-factor
 * authentication: a stolen token alone can't give the store away. A store
 * has at most one pending transfer.
 */
final readonly class StartOwnershipTransfer
{
    /**
     * Same key in every store: each store's transfers live in its own database, so the lock never spans stores.
     */
    private const string LOCK_KEY = 'ownership-transfer';

    public function __construct(
        private CurrentPasswordCheck $currentPasswordCheck,
        private TwoFactorAuthenticator $twoFactorAuthenticator,
        private StaffAuthority $staffAuthority,
    ) {}

    /**
     * @param  list<Role>  $keptRoles  The roles the owner keeps once the colleague accepts; may be none.
     *
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws InvalidTwoFactorCodeException When the owner uses two-factor authentication and the code is missing, wrong or used.
     * @throws TooManyIncorrectAttemptsException After too many wrong passwords or codes.
     * @throws InvalidOwnershipTransferRecipientException When the colleague is the owner or is deactivated.
     * @throws RoleNotAssignableException When one of the kept roles is Owner.
     * @throws OwnershipTransferAlreadyPendingException When another transfer is still waiting.
     */
    public function handle(StaffMember $owner, StaffMember $recipient, string $currentPassword, ?string $code, ?string $recoveryCode, array $keptRoles): OwnershipTransfer
    {
        $this->ensureItIsTheOwner($owner, $currentPassword, $code, $recoveryCode);
        $this->ensureCanReceiveTheStore($owner, $recipient);
        $this->staffAuthority->ensureCanGiveRoles($owner, $keptRoles);

        return OwnershipTransfer::query()->getConnection()->transaction(function () use ($owner, $recipient, $keptRoles): OwnershipTransfer {
            OwnershipTransfer::query()->getConnection()->select('select pg_advisory_xact_lock(hashtext(?))', [self::LOCK_KEY]);
            $this->ensureNoneIsPending();

            $ownershipTransfer = new OwnershipTransfer(['expires_at' => CarbonImmutable::now()->addHours(config()->integer('auth.ownership_transfers.expire_hours'))]);
            $ownershipTransfer->status = OwnershipTransferStatus::Pending;
            $ownershipTransfer->fromStaffMember()->associate($owner);
            $ownershipTransfer->toStaffMember()->associate($recipient);
            $ownershipTransfer->save();
            $ownershipTransfer->keptRoles()->sync(array_map(static fn (Role $role): int => $role->id, $keptRoles));

            activity('team')
                ->causedBy($owner)
                ->performedOn($ownershipTransfer)
                ->event('ownership_transfer_started')
                ->withProperties(['to' => $recipient->public_id, 'kept_roles' => array_map(static fn (Role $role): string => $role->name, $keptRoles)])
                ->log("Offered to make {$recipient->name} the owner of the store");

            $recipient->notify(OwnershipTransferOfferNotification::forTransfer($ownershipTransfer->public_id, $owner->name));

            return $ownershipTransfer->load(['fromStaffMember', 'toStaffMember', 'keptRoles']);
        });
    }

    /**
     * The password always, and a code too when the owner uses two-factor authentication.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws InvalidTwoFactorCodeException
     * @throws TooManyIncorrectAttemptsException
     */
    private function ensureItIsTheOwner(StaffMember $owner, string $currentPassword, ?string $code, ?string $recoveryCode): void
    {
        $this->currentPasswordCheck->ensureCorrect($owner, $currentPassword);

        if ($owner->hasTwoFactorEnabled()) {
            $this->twoFactorAuthenticator->ensureValidCode($owner, $code, $recoveryCode);
        }
    }

    /**
     * @throws InvalidOwnershipTransferRecipientException
     */
    private function ensureCanReceiveTheStore(StaffMember $owner, StaffMember $recipient): void
    {
        if ($recipient->is($owner) || ! $recipient->is_active) {
            throw new InvalidOwnershipTransferRecipientException;
        }
    }

    /**
     * A pending transfer past its time is marked expired here, so it no longer blocks a new one.
     *
     * @throws OwnershipTransferAlreadyPendingException
     */
    private function ensureNoneIsPending(): void
    {
        $markedPending = OwnershipTransfer::query()->markedPending()->lockForUpdate()->first();

        if ($markedPending === null) {
            return;
        }

        if ($markedPending->isPending()) {
            throw new OwnershipTransferAlreadyPendingException;
        }

        $markedPending->status = OwnershipTransferStatus::Expired;
        $markedPending->save();
    }
}
