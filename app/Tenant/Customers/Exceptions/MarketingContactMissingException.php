<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when consent is given or withdrawn for a channel the account has no contact on, such as text messages without a phone number.
 */
final class MarketingContactMissingException extends DomainException
{
    public function errorCode(): string
    {
        return 'marketing_contact_missing';
    }
}
