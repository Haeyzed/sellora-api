<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * A way the store can send marketing, each with its own consent.
 */
enum MarketingChannel: string
{
    /** To the email address. */
    case Email = 'email';

    /** Text messages to the phone number. */
    case Sms = 'sms';
}
