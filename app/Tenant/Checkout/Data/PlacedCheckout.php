<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Models\Payment;

/**
 * What placing a checkout came to: its order, and how the buyer pays what is left.
 */
final readonly class PlacedCheckout
{
    /**
     * @param  bool  $isNew  False when the checkout had already become this order (a second press, or a retry).
     * @param  Payment|null  $payment  The payment started for what is left: on a gateway's page, or by hand; null when nothing is left to pay or the gateway couldn't start one.
     * @param  OfferedPaymentMethod|null  $paymentMethod  The method chosen to pay what is left, as offered.
     * @param  string|null  $orderToken  A guest's order access token, only when the order was placed now: shown once, never stored.
     * @param  string|null  $paymentError  Why the gateway payment couldn't start, as an error code; the order stands, and can be paid again.
     */
    public function __construct(
        public Checkout $checkout,
        public Order $order,
        public bool $isNew,
        public ?Payment $payment,
        public ?OfferedPaymentMethod $paymentMethod,
        public ?string $orderToken,
        public ?string $paymentError,
    ) {}
}
