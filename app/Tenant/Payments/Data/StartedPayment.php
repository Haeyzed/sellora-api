<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

/**
 * Where to send the buyer to pay on the gateway's hosted page.
 */
final readonly class StartedPayment
{
    public function __construct(
        public string $redirectUrl,
        public ?string $gatewayReference = null,
    ) {}
}
