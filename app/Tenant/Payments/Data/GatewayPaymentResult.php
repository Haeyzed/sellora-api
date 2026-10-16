<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;

/**
 * Where a payment stands, as the gateway answered when asked server-side. Anything it left out is null, never guessed.
 */
final readonly class GatewayPaymentResult
{
    /**
     * @param  Money|null  $amount  What the gateway says was paid, converted by the integration from its own format; null when it didn't say, or said it unclearly.
     * @param  GatewayMode|null  $mode  Whether the gateway says it was a test or live payment; null when it didn't say.
     * @param  string|null  $cardBrand  Such as "visa", only if the gateway gives it.
     * @param  string|null  $cardLast4  Only if the gateway gives it; never more of the card.
     */
    public function __construct(
        public GatewayPaymentState $state,
        public ?Money $amount = null,
        public ?GatewayMode $mode = null,
        public ?string $gatewayReference = null,
        public ?string $failureCode = null,
        public ?string $cardBrand = null,
        public ?string $cardLast4 = null,
    ) {}
}
