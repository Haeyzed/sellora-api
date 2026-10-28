<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use Illuminate\Database\Eloquent\Collection;

/**
 * The store's own deliveries of an order that its buyer may follow: only those on the way.
 */
final class DeliveryTracking
{
    /**
     * @return Collection<int, Delivery> With their driver, shipment and latest location.
     */
    public function onTheWay(Order $order): Collection
    {
        return Delivery::query()
            ->where('status', DeliveryStatus::OnTheWay->value)
            ->whereIn('shipment_id', Shipment::query()->select('id')->where('order_id', $order->id))
            ->with(['driver', 'shipment', 'latestLocation'])
            ->orderBy('id')
            ->get();
    }
}
