<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Returns\Enums\ReturnRefundStatus;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Events\ReturnRefundStarted;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnRefund;

/**
 * Keeps a return's refund attempts in step with Payments, which alone says what went back, for the Actions and listeners that hold the return's lock.
 *
 * The return completes on the first attempt whose refund went back in full,
 * and only once: a later success (such as a refund the gateway reported as
 * failed that went through after staff tried again) is recorded on its
 * attempt and changes nothing else here; Payments and Orders tell staff and
 * flag the order.
 */
final readonly class ReturnRefundAttempts
{
    public function __construct(private ReturnProgress $returnProgress) {}

    /**
     * Brings each attempt of a locked return up to date with its refund, and completes the return if one went back in full. Inside the caller's transaction, which holds the return's lock.
     */
    public function settle(OrderReturn $locked, ?AccountReference $by = null): void
    {
        foreach ($locked->refunds()->get() as $attempt) {
            $this->follow($locked, $attempt);
        }

        $refundable = $locked->status === ReturnStatus::Inspected || ($locked->status === ReturnStatus::Approved && $locked->keep_items);

        if ($refundable && $locked->refunds()->where('status', ReturnRefundStatus::Succeeded->value)->exists()) {
            $this->returnProgress->move($locked, ReturnStatus::Completed, $by);
        }
    }

    /**
     * Whether any attempt of a locked return may still have given money back (anything but refused or failed), once brought up to date. Such a return can't be rejected or cancelled.
     */
    public function mayHaveRefunded(OrderReturn $locked): bool
    {
        $this->settle($locked);

        return $locked->refunds()->whereNotIn('status', [ReturnRefundStatus::Refused->value, ReturnRefundStatus::Failed->value])->exists();
    }

    /**
     * What an attempt's refund amounts to, from its parts: in review if any part is, pending if any part still is, succeeded or failed when all parts are, and mixed when some went back and some failed.
     */
    public static function outcome(Refund $refund): ReturnRefundStatus
    {
        $parts = $refund->paymentRefunds;

        return match (true) {
            $parts->contains(static fn (PaymentRefund $part): bool => $part->status === RefundStatus::Pending && $part->needs_review_at !== null) => ReturnRefundStatus::InReview,
            $parts->contains(static fn (PaymentRefund $part): bool => $part->status === RefundStatus::Pending) => ReturnRefundStatus::Pending,
            $parts->every(static fn (PaymentRefund $part): bool => $part->status === RefundStatus::Succeeded) => ReturnRefundStatus::Succeeded,
            $parts->every(static fn (PaymentRefund $part): bool => $part->status === RefundStatus::Failed) => ReturnRefundStatus::Failed,
            default => ReturnRefundStatus::Mixed,
        };
    }

    /**
     * Finds the attempt's refund (by its key, if Payments made it but the attempt never heard back) and records where it stands. Announces ReturnRefundStarted the first time its refund is found.
     */
    private function follow(OrderReturn $return, ReturnRefund $attempt): void
    {
        if ($attempt->status === ReturnRefundStatus::Refused) {
            return;
        }

        $refund = Refund::query()->with('paymentRefunds')
            ->when(
                $attempt->refund_id === null,
                static fn ($query) => $query->where('order_id', $return->order_id)->where('idempotency_key', $attempt->idempotency_key),
                static fn ($query) => $query->whereKey($attempt->refund_id),
            )
            ->first();

        if ($refund === null) {
            return;
        }

        $found = $attempt->refund_id === null;
        $attempt->forceFill(['refund_id' => $refund->id, 'status' => self::outcome($refund)])->save();

        if ($found) {
            ReturnRefundStarted::dispatch($return, $attempt);
        }
    }
}
