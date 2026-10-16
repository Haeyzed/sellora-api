<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;
use App\Tenant\Payments\Enums\GatewayRefundState;

/**
 * Where a refund stands, as the gateway answered.
 */
final readonly class GatewayRefundResult
{
    /**
     * @param  Money|null  $amount  What the gateway says it refunded; null when it didn't say.
     */
    public function __construct(
        public GatewayRefundState $state,
        public ?string $gatewayRefundReference = null,
        public ?Money $amount = null,
        public ?string $failureCode = null,
    ) {}
}
