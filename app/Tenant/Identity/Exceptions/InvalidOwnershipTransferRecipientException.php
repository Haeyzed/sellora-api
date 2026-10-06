<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when the store is offered to someone who can't take it: the owner themselves, or a deactivated staff member.
 */
final class InvalidOwnershipTransferRecipientException extends DomainException
{
    public function errorCode(): string
    {
        return 'ownership_transfer_recipient_invalid';
    }

    public function fieldErrors(): array
    {
        return ['staff_member' => [$this->translatedMessage()]];
    }
}
