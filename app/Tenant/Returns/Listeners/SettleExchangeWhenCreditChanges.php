<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Listeners;

use App\Shared\Exceptions\DomainException;
use App\Tenant\Checkout\Events\ExchangeCreditCommitted;
use App\Tenant\Checkout\Events\ExchangeCreditReleased;
use App\Tenant\Returns\Actions\SettleExchange;
use App\Tenant\Returns\Enums\ReturnExchangeStatus;
use App\Tenant\Returns\Events\ExchangeSettlementRefused;
use App\Tenant\Returns\Models\ReturnExchange;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * When an exchange's replacement order is paid, or expires or is cancelled unpaid, settles the return it replaces (Step 18d).
 *
 * Runs after Checkout commits the credit, in transactions of its own, so the
 * return is locked before the order. A refusal from Payments never undoes
 * the credit, which is final: it is logged (public IDs only), the error
 * tracker is told, ExchangeSettlementRefused alerts staff, and staff settle
 * it again from the return.
 */
final readonly class SettleExchangeWhenCreditChanges implements ShouldHandleEventsAfterCommit
{
    public function __construct(private SettleExchange $settleExchange) {}

    public function handle(ExchangeCreditCommitted|ExchangeCreditReleased $event): void
    {
        $exchange = ReturnExchange::query()->where('order_id', $event->hold->order_id)->where('status', ReturnExchangeStatus::Pending->value)->first();

        if ($exchange === null) {
            return;
        }

        if ($event instanceof ExchangeCreditReleased) {
            $this->settleExchange->released($exchange);

            return;
        }

        try {
            $this->settleExchange->committed($exchange);
        } catch (DomainException $exception) {
            report($exception);
            Log::warning('An exchange\'s replacement was paid, but settling its return failed; staff settle it again from the return.', ['exchange' => $exchange->public_id, 'reason' => $exception->errorCode()]);
            ExchangeSettlementRefused::dispatch($exchange, $exception->errorCode());
        }
    }
}
