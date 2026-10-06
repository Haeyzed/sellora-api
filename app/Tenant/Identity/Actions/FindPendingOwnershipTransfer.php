<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Tenant\Identity\Exceptions\OwnershipTransferNotFoundException;
use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * The store's pending ownership transfer, for the two people it concerns: the owner who offered it and the colleague offered the store.
 */
final readonly class FindPendingOwnershipTransfer
{
    /**
     * @throws OwnershipTransferNotFoundException When none is pending, or the staff member is neither of the two.
     */
    public function handle(StaffMember $staffMember): OwnershipTransfer
    {
        $ownershipTransfer = OwnershipTransfer::query()
            ->markedPending()
            ->where(static function (Builder $query) use ($staffMember): void {
                $query->where('from_staff_member_id', $staffMember->id)->orWhere('to_staff_member_id', $staffMember->id);
            })
            ->with(['fromStaffMember', 'toStaffMember', 'keptRoles'])
            ->first();

        if ($ownershipTransfer === null || ! $ownershipTransfer->isPending()) {
            throw new OwnershipTransferNotFoundException;
        }

        return $ownershipTransfer;
    }
}
