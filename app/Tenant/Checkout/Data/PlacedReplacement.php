<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;

/**
 * A replacement order as placed (Step 18c), with the exchange credit it holds.
 */
final readonly class PlacedReplacement
{
    /**
     * @param  bool  $isNew  False when the return already had this replacement: placing again returns it.
     * @param  CheckoutPaymentHold|null  $credit  What the returned items hold of it; null when they credit nothing.
     */
    public function __construct(
        public Order $order,
        public bool $isNew,
        public ?CheckoutPaymentHold $credit,
    ) {}
}
