<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Exceptions\DriverDeactivatedException;
use App\Tenant\Delivery\Exceptions\DriverErasedException;
use App\Tenant\Delivery\Exceptions\ShipmentCannotBeAssignedException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DeliveryActivity;
use App\Tenant\Delivery\Services\DeliveryProgress;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Staff giving a delivery to another driver before the first one picks it up: the first is cancelled, and the other driver gets a new one.
 *
 * Once picked up, the goods are with the first driver: fail the delivery and
 * bring the goods back to the store first.
 */
final readonly class ReassignDelivery
{
    public function __construct(
        private AssignDriver $assignDriver,
        private DeliveryProgress $deliveryProgress,
        private DeliveryActivity $deliveryActivity,
    ) {}

    /**
     * @return Delivery The new delivery.
     *
     * @throws DeliveryTransitionNotAllowedException When it has been picked up, or isn't under way.
     * @throws DriverDeactivatedException
     * @throws DriverErasedException
     * @throws ShipmentCannotBeAssignedException
     */
    public function handle(StaffMember $actor, Delivery $delivery, Driver $driver): Delivery
    {
        [$cancelled, $assigned] = DB::transaction(function () use ($actor, $delivery, $driver): array {
            $lockedDriver = $this->assignDriver->lockAvailable($driver);
            $locked = $this->deliveryProgress->lock($delivery);

            if ($locked->driver_id === $lockedDriver->id) {
                throw new DeliveryTransitionNotAllowedException;
            }

            $this->deliveryProgress->move($locked, DeliveryStatus::Cancelled, AccountReference::to($actor));

            return [$locked, $this->assignDriver->assignLocked($actor, $locked->shipment, $lockedDriver)];
        });

        $this->deliveryActivity->record($actor, $cancelled, 'delivery_reassigned', 'Reassigned');
        $this->deliveryActivity->record($actor, $assigned, 'delivery_assigned', 'Assigned');

        return $assigned;
    }
}
