<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * What happened to a payment, in its history.
 */
enum PaymentEventType: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case NeedsReview = 'needs_review';
    case Unresolved = 'unresolved';
    case Cancelled = 'cancelled';
    /** The buyer disputed it with their bank; no money state changed. */
    case Disputed = 'disputed';
    /** More arrived than was expected: it all counts, and the difference is owed back. */
    case Overpaid = 'overpaid';
    /** Staff were told cash wasn't recorded as collected a while after delivery. */
    case CollectionOverdue = 'collection_overdue';
    /** Part of a refund was asked of the gateway, or recorded as paid back by hand. */
    case RefundStarted = 'refund_started';
    /** Part of a refund went back to the buyer. */
    case Refunded = 'refunded';
    /** Part of a refund failed at the gateway; nothing went back. */
    case RefundFailed = 'refund_failed';
    /** The gateway reported a refund as failed, then as gone through: it counts, and staff were told. */
    case RefundSucceededAfterFailing = 'refund_succeeded_after_failing';
    /** Staff were told a refund was still pending at the gateway after days; it is asked about daily from then on. */
    case RefundStillPending = 'refund_still_pending';
    /** Part of a refund went to staff review: the gateway's answers can't tell whether it was made, so it is never sent again. */
    case RefundNeedsReview = 'refund_needs_review';
}
