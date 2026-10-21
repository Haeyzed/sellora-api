<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when asking for a link emailed to the existing account, when the provider isn't trusted or didn't say the email is verified: only the account's password proves ownership then.
 */
final class SocialLinkEmailNotAllowedException extends DomainException
{
    public function errorCode(): string
    {
        return 'social_link_email_not_allowed';
    }
}
