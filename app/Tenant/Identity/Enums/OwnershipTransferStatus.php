<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Enums;

/**
 * Where an ownership transfer is: waiting for the new owner, accepted, cancelled by the owner, or expired unanswered.
 */
enum OwnershipTransferStatus: string
{
    /** Waiting for the chosen staff member to accept. Nothing has changed yet. */
    case Pending = 'pending';

    /** The new owner accepted; they own the store now. */
    case Accepted = 'accepted';

    /** The owner cancelled it before it was accepted. */
    case Cancelled = 'cancelled';

    /** Nobody accepted it in time. */
    case Expired = 'expired';
}
