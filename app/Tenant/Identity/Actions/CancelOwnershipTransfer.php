<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Tenant\Identity\Enums\OwnershipTransferStatus;
use App\Tenant\Identity\Exceptions\OwnershipTransferNotPendingException;
use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;

/**
 * The owner withdraws their offer before it is accepted. The row is locked, so it can't be accepted and cancelled at once.
 */
final readonly class CancelOwnershipTransfer
{
    /**
     * @throws OwnershipTransferNotPendingException When it was already accepted, cancelled or has expired.
     */
    public function handle(StaffMember $owner, OwnershipTransfer $ownershipTransfer): void
    {
        OwnershipTransfer::query()->getConnection()->transaction(static function () use ($owner, $ownershipTransfer): void {
            $locked = OwnershipTransfer::query()->whereKey($ownershipTransfer->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new OwnershipTransferNotPendingException;
            }

            $locked->forceFill(['status' => OwnershipTransferStatus::Cancelled, 'cancelled_at' => CarbonImmutable::now()])->save();

            activity('team')
                ->causedBy($owner)
                ->performedOn($locked)
                ->event('ownership_transfer_cancelled')
                ->log('Cancelled the ownership transfer');
        });
    }
}
