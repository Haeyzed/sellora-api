<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Features\FeatureState;
use App\Tenant\Payments\Contracts\PaymentGateway;
use App\Tenant\Payments\Models\PaymentGatewayConnection;

/**
 * A payment gateway the platform offers, as staff see it: whether the store's plan includes it, and how the store is connected to it, if at all.
 */
final readonly class OfferedPaymentGateway
{
    public function __construct(
        public PaymentGateway $gateway,
        public FeatureState $availability,
        public ?PaymentGatewayConnection $connection,
    ) {}
}
