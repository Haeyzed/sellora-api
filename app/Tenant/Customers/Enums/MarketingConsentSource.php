<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * Where a consent answer was given.
 */
enum MarketingConsentSource: string
{
    /** The customer, in their account. */
    case Account = 'account';

    /** A customer or a guest, at checkout (Step 16). */
    case Checkout = 'checkout';

    /** Staff, recording a withdrawal the customer asked for. Staff never grant consent. */
    case Staff = 'staff';
}
