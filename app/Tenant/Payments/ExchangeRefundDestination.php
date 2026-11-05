<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Contracts\RefundDestination;
use App\Tenant\Payments\Data\DestinationRefund;

/**
 * Core's destination for the part of a return that pays for its exchange's replacement order (Step 18c): the money stays in the store, as what the returned items credited toward the replacement.
 *
 * Never offered: neither a customer nor staff can choose it; only an
 * exchange refunds to it, for the credit its replacement order already
 * committed. Nothing is paid out anywhere, so it never fails, and the same
 * refund always gets the same reference.
 */
final readonly class ExchangeRefundDestination implements RefundDestination
{
    public function key(): string
    {
        return RefundDestinations::EXCHANGE;
    }

    public function label(): string
    {
        $label = __('refunds.destinations.'.RefundDestinations::EXCHANGE);

        return is_string($label) ? $label : RefundDestinations::EXCHANGE;
    }

    public function isOfferedFor(Order $order, Customer $customer): bool
    {
        return false;
    }

    public function giveBack(DestinationRefund $refund): string
    {
        return RefundDestinations::EXCHANGE.':'.$refund->refundId;
    }
}
