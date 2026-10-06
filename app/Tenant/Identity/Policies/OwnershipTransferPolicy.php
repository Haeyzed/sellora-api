<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Policies;

use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAuthority;

/**
 * Only the owner offers or withdraws the store; only the colleague it was offered to accepts it.
 *
 * The owner passes every check through the full-access rule, so accepting is
 * checked again in the Action: an owner can't accept an offer made to someone else.
 */
final readonly class OwnershipTransferPolicy
{
    public function __construct(private StaffAuthority $staffAuthority) {}

    public function create(StaffMember $actor): bool
    {
        return $this->staffAuthority->isOwner($actor);
    }

    public function cancel(StaffMember $actor, OwnershipTransfer $ownershipTransfer): bool
    {
        return $this->staffAuthority->isOwner($actor) && $ownershipTransfer->from_staff_member_id === $actor->id;
    }

    public function accept(StaffMember $actor, OwnershipTransfer $ownershipTransfer): bool
    {
        return $ownershipTransfer->to_staff_member_id === $actor->id;
    }
}
