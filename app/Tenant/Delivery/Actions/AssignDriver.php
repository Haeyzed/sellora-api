<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Exceptions\DriverDeactivatedException;
use App\Tenant\Delivery\Exceptions\DriverErasedException;
use App\Tenant\Delivery\Exceptions\ShipmentCannotBeAssignedException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DeliveryActivity;
use App\Tenant\Delivery\Services\DeliveryProgress;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Staff giving one of the store's own deliveries to a driver: a shipment the store delivers itself that hasn't left yet.
 *
 * Takes a share lock on the driver first, so they can't be deactivated
 * meanwhile, then the order's and the shipment's locks, which staff marking
 * the shipment shipped also take: two assignments of one shipment at once
 * give it one delivery, and staff can't ship it behind the driver's back.
 * Assigning a shipment whose earlier delivery was cancelled is a new attempt.
 */
final readonly class AssignDriver
{
    public function __construct(
        private DeliveryProgress $deliveryProgress,
        private DeliveryActivity $deliveryActivity,
    ) {}

    /**
     * @throws ShipmentCannotBeAssignedException When it isn't an own delivery waiting to leave, or already has a delivery under way.
     * @throws DriverDeactivatedException
     * @throws DriverErasedException
     */
    public function handle(StaffMember $actor, Shipment $shipment, Driver $driver): Delivery
    {
        $delivery = DB::transaction(function () use ($actor, $shipment, $driver): Delivery {
            $lockedDriver = $this->lockAvailable($driver);

            return $this->assignLocked($actor, $shipment, $lockedDriver);
        });

        $this->deliveryActivity->record($actor, $delivery, 'delivery_assigned', 'Assigned');

        return $delivery;
    }

    /**
     * The driver, share-locked until the transaction ends, if they can take deliveries.
     *
     * @throws DriverDeactivatedException
     * @throws DriverErasedException
     */
    public function lockAvailable(Driver $driver): Driver
    {
        $locked = Driver::query()->whereKey($driver->id)->sharedLock()->firstOrFail();

        if ($locked->isErased()) {
            throw new DriverErasedException;
        }

        if (! $locked->is_active) {
            throw new DriverDeactivatedException;
        }

        return $locked;
    }

    /**
     * Assigns with the driver already locked, inside the caller's transaction.
     *
     * @throws ShipmentCannotBeAssignedException
     */
    public function assignLocked(StaffMember $actor, Shipment $shipment, Driver $lockedDriver): Delivery
    {
        Order::query()->whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
        $lockedShipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
        $deliveries = Delivery::query()->where('shipment_id', $lockedShipment->id);

        if ($lockedShipment->fulfilment !== ShippingFulfilment::OwnDelivery || $lockedShipment->status !== ShipmentStatus::Pending || (clone $deliveries)->open()->exists()) {
            throw new ShipmentCannotBeAssignedException;
        }

        $by = AccountReference::to($actor);
        $delivery = new Delivery;
        $delivery->forceFill([
            'shipment_id' => $lockedShipment->id,
            'driver_id' => $lockedDriver->id,
            'attempt' => ((int) (clone $deliveries)->max('attempt')) + 1,
            'status' => DeliveryStatus::Assigned,
            'assigned_at' => CarbonImmutable::now(),
            'assigned_by_type' => $by->type,
            'assigned_by_id' => $by->publicId,
        ])->save();
        $this->deliveryProgress->record($delivery, null, DeliveryStatus::Assigned, $by);

        return $delivery;
    }
}
