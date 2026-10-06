<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Enums;

/**
 * Where a store's subscription stands, which decides whether the store keeps access to its plan.
 */
enum SubscriptionStatus: string
{
    /** Trying the plan before paying. */
    case Trialing = 'trialing';

    /** Paid and in good standing. */
    case Active = 'active';

    /** A payment failed; the store keeps access during a grace period, then is suspended. */
    case PastDue = 'past_due';

    /** Access paused by the platform, for example after the grace period or for abuse. Data is kept. */
    case Suspended = 'suspended';

    /** The merchant cancelled; access continues until the end of the paid period. */
    case Cancelled = 'cancelled';
}
