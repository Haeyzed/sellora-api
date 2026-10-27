<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Delivery\Enums\DeliveryFailureReason;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Services\DeliveryProgress;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Exceptions\ShipmentTransitionNotAllowedException;
use Illuminate\Support\Facades\DB;

/**
 * A driver moving their delivery along, or staff doing it for them: picked up, on the way, delivered, or failed.
 *
 * Each move locks the delivery first, then Orders locks the order and the
 * shipment, so the same delivery moved twice at once moves once. Picking up
 * ships the shipment: its stock leaves the shelf. Delivering delivers it,
 * with the name of who took the goods. A failed delivery keeps the goods with
 * the driver until staff retry it or take the goods back.
 */
final readonly class MoveDelivery
{
    public function __construct(
        private DeliveryProgress $deliveryProgress,
        private MoveShipment $moveShipment,
    ) {}

    /**
     * @throws DeliveryTransitionNotAllowedException
     * @throws ShipmentTransitionNotAllowedException When its shipment can no longer ship, such as one cancelled meanwhile.
     * @throws InsufficientStockException When what it holds isn't on hand.
     */
    public function pickUp(Delivery $delivery, AccountReference $by): Delivery
    {
        return $this->transition($delivery, DeliveryStatus::PickedUp, $by, function (Delivery $locked) use ($by): void {
            // A retried delivery's shipment has already left with the driver.
            if ($locked->shipment->status === ShipmentStatus::Pending) {
                $this->moveShipment->ship($locked->shipment, $by, byDriver: true);
            }
        });
    }

    /**
     * @throws DeliveryTransitionNotAllowedException
     */
    public function onTheWay(Delivery $delivery, AccountReference $by): Delivery
    {
        return $this->transition($delivery, DeliveryStatus::OnTheWay, $by);
    }

    /**
     * @param-immediately-invoked-callable $alsoDo
     *
     * @param  (callable(Delivery): void)|null  $alsoDo  Anything else to do under the same locks, such as recording cash collected (Step 17).
     *
     * @throws DeliveryTransitionNotAllowedException
     * @throws ShipmentTransitionNotAllowedException
     */
    public function deliver(Delivery $delivery, AccountReference $by, string $recipientName, ?callable $alsoDo = null): Delivery
    {
        return $this->transition($delivery, DeliveryStatus::Delivered, $by, function (Delivery $locked) use ($by, $alsoDo): void {
            $this->moveShipment->deliver($locked->shipment, $by, byDriver: true);

            if ($alsoDo !== null) {
                $alsoDo($locked);
            }
        }, ['recipient_name' => $recipientName]);
    }

    /**
     * @throws DeliveryTransitionNotAllowedException
     */
    public function fail(Delivery $delivery, AccountReference $by, DeliveryFailureReason $reason, ?string $note): Delivery
    {
        return $this->transition($delivery, DeliveryStatus::Failed, $by, fields: ['failure_reason' => $reason, 'failure_note' => $note]);
    }

    /**
     * @param-immediately-invoked-callable $before
     *
     * @param  (callable(Delivery): void)|null  $before  What else happens once the move is allowed, before it is saved.
     * @param  array<string, mixed>  $fields
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    private function transition(Delivery $delivery, DeliveryStatus $next, AccountReference $by, ?callable $before = null, array $fields = []): Delivery
    {
        return DB::transaction(function () use ($delivery, $next, $by, $before, $fields): Delivery {
            $locked = $this->deliveryProgress->lock($delivery);
            $this->deliveryProgress->ensureCanMove($locked, $next);

            if ($before !== null) {
                $before($locked);
            }

            return $this->deliveryProgress->move($locked, $next, $by, $fields);
        });
    }
}
