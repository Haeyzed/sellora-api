<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\OfferedRefundDestination;
use App\Tenant\Payments\RefundDestinations;

/**
 * Where an order's refunds may go now, for the customer whose order it is (section 13): always back to the original payment, and each module's destination on offer to them. A guest, or anyone but the order's own customer, is offered the original payment only.
 */
final readonly class FindRefundDestinations
{
    public function __construct(private RefundDestinations $refundDestinations) {}

    /**
     * @return list<OfferedRefundDestination> The original payment first.
     */
    public function handle(Order $order, ?Customer $customer): array
    {
        return $this->refundDestinations->offeredFor($order, $customer);
    }

    /**
     * Whether a destination is on offer now for this order and customer.
     */
    public function isOffered(string $key, Order $order, ?Customer $customer): bool
    {
        return $this->refundDestinations->isOffered($key, $order, $customer);
    }

    /**
     * What a destination is called in the current language, or its key for one no module registers any more.
     */
    public function labelOf(string $key): string
    {
        return $this->refundDestinations->labelOf($key);
    }
}
