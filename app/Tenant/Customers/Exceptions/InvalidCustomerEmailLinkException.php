<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when an emailed customer link is unknown, expired, already used, for another account, or for an address that is no longer the account's. The same error for each, so it reveals nothing.
 */
final class InvalidCustomerEmailLinkException extends DomainException
{
    public function errorCode(): string
    {
        return 'customer_email_link_invalid';
    }
}
