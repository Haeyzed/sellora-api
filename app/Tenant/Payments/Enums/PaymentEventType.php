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
}
