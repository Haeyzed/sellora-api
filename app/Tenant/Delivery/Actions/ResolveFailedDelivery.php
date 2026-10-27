<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Delivery\Enums\DeliveryResolution;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Services\DeliveryActivity;
use App\Tenant\Delivery\Services\DeliveryProgress;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Actions\MoveShipment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Staff resolving a failed delivery, whose goods are still with the driver: the same driver tries again, or the goods come back to the store.
 *
 * Trying again is a new delivery for the same driver, starting picked up,
 * since the goods never left them. Bringing the goods back returns the
 * shipment (Orders puts its stock back on the shelf, held for the order), so
 * it can be shipped again later, perhaps with another driver. Either way the
 * failed delivery is resolved once, under its lock.
 */
final readonly class ResolveFailedDelivery
{
    public function __construct(
        private DeliveryProgress $deliveryProgress,
        private DeliveryActivity $deliveryActivity,
        private MoveShipment $moveShipment,
    ) {}

    /**
     * @return Delivery The new attempt.
     *
     * @throws DeliveryTransitionNotAllowedException When it hasn't failed, or was already resolved.
     */
    public function retry(StaffMember $actor, Delivery $delivery): Delivery
    {
        $retry = DB::transaction(function () use ($actor, $delivery): Delivery {
            $by = AccountReference::to($actor);
            $locked = $this->resolve($delivery, DeliveryResolution::Retried);
            $now = CarbonImmutable::now();
            $retry = new Delivery;
            $retry->forceFill([
                'shipment_id' => $locked->shipment_id,
                'driver_id' => $locked->driver_id,
                'attempt' => $locked->attempt + 1,
                'status' => DeliveryStatus::PickedUp,
                'assigned_at' => $now,
                'picked_up_at' => $now,
                'assigned_by_type' => $by->type,
                'assigned_by_id' => $by->publicId,
            ])->save();
            $this->deliveryProgress->record($retry, null, DeliveryStatus::PickedUp, $by);

            return $retry;
        });

        $this->deliveryActivity->record($actor, $retry, 'delivery_retried', 'Sent out again as');

        return $retry;
    }

    /**
     * The driver brought the goods back to the store.
     *
     * @throws DeliveryTransitionNotAllowedException When it hasn't failed, or was already resolved.
     */
    public function returnToStore(StaffMember $actor, Delivery $delivery): Delivery
    {
        $returned = DB::transaction(function () use ($actor, $delivery): Delivery {
            $locked = $this->resolve($delivery, DeliveryResolution::Returned);
            $this->moveShipment->returnToStore($locked->shipment, AccountReference::to($actor));

            return $locked;
        });

        $this->deliveryActivity->record($actor, $returned, 'delivery_returned', 'Took back the goods of');

        return $returned;
    }

    /**
     * @throws DeliveryTransitionNotAllowedException
     */
    private function resolve(Delivery $delivery, DeliveryResolution $resolution): Delivery
    {
        $locked = $this->deliveryProgress->lock($delivery);

        if ($locked->status !== DeliveryStatus::Failed || $locked->resolved_at !== null) {
            throw new DeliveryTransitionNotAllowedException;
        }

        $locked->forceFill(['resolved_at' => CarbonImmutable::now(), 'resolution' => $resolution])->save();

        return $locked;
    }
}
