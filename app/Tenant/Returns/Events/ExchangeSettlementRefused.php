<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Events;

use App\Tenant\Returns\Models\ReturnExchange;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An exchange's replacement was paid, but settling its return was refused (by Payments, for the refund to the exchange or the difference): the return waits for its exchange until staff settle it again from the return (Step 18d). Announced for Messaging to alert staff.
 */
final class ExchangeSettlementRefused implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $reason  The refusal's error code, such as "payment_method_cannot_refund".
     */
    public function __construct(
        public readonly ReturnExchange $exchange,
        public readonly string $reason,
    ) {}
}
