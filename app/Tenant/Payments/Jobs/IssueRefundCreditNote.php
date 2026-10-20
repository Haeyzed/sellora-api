<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Jobs;

use App\Tenant\Orders\Actions\IssueCreditNote;
use App\Tenant\Orders\Exceptions\CreditNoteExceedsInvoiceException;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Services\RefundCredits;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Issues the credit note for a part of a refund that reduced the sale, once it has gone back, on the default queue, in its store. Safe to run twice; the hourly sweep issues any it missed.
 */
final class IssueRefundCreditNote implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $paymentRefundId) {}

    public function handle(RefundCredits $refundCredits, IssueCreditNote $issueCreditNote): void
    {
        $paymentRefund = PaymentRefund::query()->find($this->paymentRefundId);
        $request = $paymentRefund === null ? null : $refundCredits->requestFor($paymentRefund);

        if ($request === null) {
            return;
        }

        try {
            $issueCreditNote->handle($request);
        } catch (CreditNoteExceedsInvoiceException $exception) {
            // Trying again can't help: staff must look at it.
            report($exception);
        }
    }
}
