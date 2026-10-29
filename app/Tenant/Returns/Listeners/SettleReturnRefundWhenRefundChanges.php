<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Listeners;

use App\Tenant\Payments\Events\RefundFailed;
use App\Tenant\Payments\Events\RefundNeedsReview;
use App\Tenant\Payments\Events\RefundSucceeded;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Returns\Models\ReturnRefund;
use App\Tenant\Returns\Services\ReturnProgress;
use App\Tenant\Returns\Services\ReturnRefundAttempts;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\DB;

/**
 * When part of a return's refund goes back, fails or goes to staff review, brings the return's attempts up to date, completing the return on the first refund that went back in full.
 *
 * Runs after Payments commits, in a transaction of its own, so the return is
 * never locked after the order (the store's lock order is the return first).
 */
final readonly class SettleReturnRefundWhenRefundChanges implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private ReturnRefundAttempts $attempts,
    ) {}

    public function handle(RefundSucceeded|RefundFailed|RefundNeedsReview $event): void
    {
        $key = Refund::query()->whereKey($event->paymentRefund->refund_id)->value('idempotency_key');
        $attempt = is_string($key) && str_starts_with($key, 'return:') ? ReturnRefund::query()->with('orderReturn')->where('idempotency_key', $key)->first() : null;

        if ($attempt === null) {
            return;
        }

        DB::transaction(fn () => $this->attempts->settle($this->returnProgress->lock($attempt->orderReturn)));
    }
}
