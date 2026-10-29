<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Enums;

/**
 * Where an attempt to refund a return stands, read from its refund's parts in Payments.
 *
 * Only a refused or failed attempt definitely got nothing back, so only
 * then may a new attempt be made. A started attempt is asked of Payments
 * again under the same key; the rest wait for Payments, or for staff.
 */
enum ReturnRefundStatus: string
{
    /** Saved, and Payments not yet heard from: asked again under the same key. */
    case Started = 'started';

    /** Payments refused it, so no refund was made. */
    case Refused = 'refused';

    /** Some of its refund hasn't gone through yet. */
    case Pending = 'pending';

    /** Payments can't tell whether part of its refund went through, and staff must look: it is never sent again. */
    case InReview = 'in_review';

    /** All of its refund went back. */
    case Succeeded = 'succeeded';

    /** Every part of its refund failed: nothing went back. */
    case Failed = 'failed';

    /** Part of its refund went back and part failed, across several payments: staff settle the rest, it is never tried again whole. */
    case Mixed = 'mixed';

    /**
     * Whether it definitely got nothing back, so a new attempt may be made.
     */
    public function allowsNewAttempt(): bool
    {
        return in_array($this, [self::Refused, self::Failed], true);
    }
}
