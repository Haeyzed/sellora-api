<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Listeners;

use App\Tenant\Payments\Enums\RefundPurpose;
use App\Tenant\Payments\Events\RefundSucceeded;
use App\Tenant\Payments\Jobs\IssueRefundCreditNote;

/**
 * Queues the credit note for a part of a refund that reduced the sale, once the transaction recording that it went back commits. A refund that returned money never part of the sale gets none.
 */
final readonly class IssueCreditNoteWhenRefundSucceeds
{
    public function handle(RefundSucceeded $event): void
    {
        if ($event->paymentRefund->refund->purpose === RefundPurpose::ReducesSale) {
            IssueRefundCreditNote::dispatch($event->paymentRefund->id)->afterCommit();
        }
    }
}
