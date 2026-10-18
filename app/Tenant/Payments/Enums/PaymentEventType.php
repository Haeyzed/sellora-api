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
}
