<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Events;

use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Recording an order's payments after its holds settled failed checkout.hold_settle_alert_after tries in a row (why is on its holds): staff are alerted once, and it is still tried again hourly until it works.
 */
final readonly class OrderPaymentsKeepFailing
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
