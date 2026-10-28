<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Events\DeliveryCancelled;
use App\Tenant\Delivery\Events\DeliveryCompleted;
use App\Tenant\Delivery\Events\DeliveryFailed;
use App\Tenant\Delivery\Events\DeliveryOnTheWay;
use App\Tenant\Delivery\Events\DeliveryPickedUp;
use App\Tenant\Delivery\Events\DriverAssigned;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DeliveryStatusChange;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Moves deliveries along for the Actions that change them, writing each status change to the delivery's history.
 *
 * Locks always go the same way: the driver, then the delivery, then (through
 * Orders) the order and the shipment, then (through Payments) the payment, so
 * two changes at the same moment never wait on each other in a circle. Each
 * change works from the delivery as saved under its lock, never from a copy
 * read earlier.
 */
final readonly class DeliveryProgress
{
    /**
     * The delivery as saved now, locked until the transaction ends.
     *
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function lock(Delivery $delivery): Delivery
    {
        if (Delivery::query()->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Deliveries must be locked inside a database transaction.');
        }

        return Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Refuses a move this locked delivery can't make.
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    public function ensureCanMove(Delivery $locked, DeliveryStatus $next): void
    {
        if (! $locked->status->canMoveTo($next)) {
            throw new DeliveryTransitionNotAllowedException;
        }
    }

    /**
     * Moves a locked delivery to the next status, stamping when, and records the change.
     *
     * @param  array<string, mixed>  $fields  Anything else to save with it, such as a failure reason.
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    public function move(Delivery $locked, DeliveryStatus $next, ?AccountReference $by, array $fields = []): Delivery
    {
        $this->ensureCanMove($locked, $next);

        $from = $locked->status;
        $now = CarbonImmutable::now();
        $locked->forceFill([...$fields, 'status' => $next, self::stampFor($next) => $now])->save();
        $this->record($locked, $from, $next, $by);

        return $locked;
    }

    /**
     * Adds a change to the delivery's history, and announces it once the transaction commits (for Messaging, Step 20).
     */
    public function record(Delivery $delivery, ?DeliveryStatus $from, DeliveryStatus $to, ?AccountReference $by): void
    {
        (new DeliveryStatusChange)->forceFill([
            'delivery_id' => $delivery->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by_type' => $by?->type,
            'changed_by_id' => $by?->publicId,
        ])->save();

        match ($to) {
            DeliveryStatus::Assigned => DriverAssigned::dispatch($delivery),
            DeliveryStatus::PickedUp => DeliveryPickedUp::dispatch($delivery),
            DeliveryStatus::OnTheWay => DeliveryOnTheWay::dispatch($delivery),
            DeliveryStatus::Delivered => DeliveryCompleted::dispatch($delivery),
            DeliveryStatus::Failed => DeliveryFailed::dispatch($delivery),
            DeliveryStatus::Cancelled => DeliveryCancelled::dispatch($delivery),
        };
    }

    private static function stampFor(DeliveryStatus $status): string
    {
        return match ($status) {
            DeliveryStatus::Assigned => 'assigned_at',
            DeliveryStatus::PickedUp => 'picked_up_at',
            DeliveryStatus::OnTheWay => 'on_the_way_at',
            DeliveryStatus::Delivered => 'delivered_at',
            DeliveryStatus::Failed => 'failed_at',
            DeliveryStatus::Cancelled => 'cancelled_at',
        };
    }
}
