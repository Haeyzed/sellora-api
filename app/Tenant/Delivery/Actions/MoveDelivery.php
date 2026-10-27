<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Money;
use App\Tenant\Delivery\Enums\DeliveryFailureReason;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Exceptions\CashCollectionRequiredException;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Services\DeliveryProgress;
use App\Tenant\Delivery\Services\DriverCashLedger;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Exceptions\ShipmentTransitionNotAllowedException;
use App\Tenant\Payments\Actions\FindCashToCollect;
use App\Tenant\Payments\Actions\RecordCashCollectedOnDelivery;
use App\Tenant\Payments\Exceptions\PaymentNotAwaitingReceiptException;
use App\Tenant\Payments\Exceptions\PaymentReceiptInvalidException;
use Illuminate\Support\Facades\DB;

/**
 * A driver moving their delivery along, or staff doing it for them: picked up, on the way, delivered, or failed.
 *
 * Each move locks the delivery first, then Orders locks the order and the
 * shipment, so the same delivery moved twice at once moves once. Picking up
 * ships the shipment: its stock leaves the shelf. Delivering delivers it,
 * with the name of who took the goods, and records any cash collected
 * (Payments then takes the payment's lock). A failed delivery keeps the
 * goods with the driver until staff retry it or take the goods back.
 */
final readonly class MoveDelivery
{
    public function __construct(
        private DeliveryProgress $deliveryProgress,
        private MoveShipment $moveShipment,
        private FindCashToCollect $findCashToCollect,
        private RecordCashCollectedOnDelivery $recordCashCollectedOnDelivery,
        private DriverCashLedger $driverCashLedger,
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
     * Handed over. When the order is paid in cash on delivery, the cash the driver collected pays it and goes into their cash ledger, all in this one transaction: the payment, the ledger entry and the delivery are saved together or not at all.
     *
     * @param  int|null  $cashCollected  In minor units of the order's currency; required when there is cash to collect.
     *
     * @throws DeliveryTransitionNotAllowedException
     * @throws ShipmentTransitionNotAllowedException
     * @throws CashCollectionRequiredException When there is cash to collect and no amount was given.
     * @throws PaymentNotAwaitingReceiptException When cash was given for an order with none to collect, such as a test order.
     * @throws PaymentReceiptInvalidException
     */
    public function deliver(Delivery $delivery, AccountReference $by, string $recipientName, ?int $cashCollected = null): Delivery
    {
        return $this->transition($delivery, DeliveryStatus::Delivered, $by, function (Delivery $locked) use ($by, $cashCollected): void {
            $this->moveShipment->deliver($locked->shipment, $by, byDriver: true);
            $order = $locked->shipment->order;

            if ($cashCollected === null) {
                if ($this->findCashToCollect->handle($order) !== null) {
                    throw new CashCollectionRequiredException;
                }

                return;
            }

            $cash = Money::ofMinor($cashCollected, $order->currency);
            $payment = $this->recordCashCollectedOnDelivery->handle($locked->driver, $order, $cash);
            $this->driverCashLedger->collected($locked->driver, $locked, $cash, $payment->public_id, $by);
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
