<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * What an emailed one-time link to a customer is for.
 */
enum CustomerEmailTokenPurpose: string
{
    /** Proves the customer receives mail at their account's email. */
    case Verification = 'verification';

    /** Moves the account to a new email, once a link sent there is used. */
    case EmailChange = 'email_change';

    /** Confirms connecting a sign-in provider to the account, when its owner proves ownership by email (Step 10b). Its token is kept on the social sign-in ticket, not with the customer's other links. */
    case SocialLink = 'social_link';

    /** Confirms a customer without a password erasing their account (section 3.3): emailed to their account address, or given after a fresh provider sign-in, for which it expires sooner. */
    case Erasure = 'erasure';

    /**
     * The key of this purpose's link settings in config/customers.php, and of its email texts in lang/<locale>/customers.php.
     */
    public function key(): string
    {
        return match ($this) {
            self::Verification => 'email_verification',
            self::EmailChange => 'email_change',
            self::SocialLink => 'social_link',
            self::Erasure => 'erasure',
        };
    }

    /**
     * How long a link stays usable, from config/customers.php.
     */
    public function expiresInMinutes(): int
    {
        return config()->integer('customers.'.$this->key().'.expire_minutes');
    }
}
