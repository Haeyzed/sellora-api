<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * What the buyer of an order sees of the store's own deliveries of it: only those on the way, each with the driver's first name and latest location. Never proof of delivery, the driver's full name or phone, or the trail.
 *
 * @mixin Order
 */
final class DeliveryTrackingResource extends JsonResource
{
    /**
     * @param  Collection<int, Delivery>  $onTheWay  Its deliveries on the way, with their driver, shipment and latest location.
     */
    public function __construct(Order $order, private readonly Collection $onTheWay)
    {
        parent::__construct($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'order' => ['id' => $order->public_id, 'number' => $order->number],
            /**
             * The store's own deliveries of the order on the way right now; empty otherwise, such as before the driver sets off or once delivered.
             *
             * @var list<array{shipment: array{id: string}, driver: array{first_name: string|null}, location: array{latitude: float, longitude: float, accuracy_meters: int|null, recorded_at: string}|null}>
             */
            'deliveries' => array_values($this->onTheWay->map(static fn (Delivery $delivery): array => [
                'shipment' => ['id' => $delivery->shipment->public_id],
                // First name only; null once the driver's personal data is erased.
                'driver' => ['first_name' => $delivery->driver->name === null ? null : Str::before(trim($delivery->driver->name), ' ')],
                // Null until the driver's app has sent a position.
                'location' => $delivery->latestLocation === null ? null : (new DriverLocationResource($delivery->latestLocation))->toArray($request),
            ])->all()),
        ];
    }
}
