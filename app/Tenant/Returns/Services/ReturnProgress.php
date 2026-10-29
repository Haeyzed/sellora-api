<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Events\ReturnApproved;
use App\Tenant\Returns\Events\ReturnCancelled;
use App\Tenant\Returns\Events\ReturnCompleted;
use App\Tenant\Returns\Events\ReturnInspected;
use App\Tenant\Returns\Events\ReturnReceived;
use App\Tenant\Returns\Events\ReturnRejected;
use App\Tenant\Returns\Events\ReturnRequested;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnStatusChange;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Moves returns along for the Actions that change them, writing each status change to the return's history and announcing it once the transaction commits.
 *
 * Locks always go the same way: the order's returns (an advisory lock, only
 * when units are claimed), then the return, then (through Payments) the order
 * and its payments, then (through Inventory) stock levels, so two changes at
 * the same moment never wait on each other in a circle. Each change works
 * from the return as saved under its lock, never from a copy read earlier.
 */
final readonly class ReturnProgress
{
    /**
     * Holds the order's returns until the transaction ends, so what can still be returned of its lines can't change meanwhile.
     *
     * @throws LogicException When called outside a transaction.
     */
    public function lockOrderReturns(Order $order): void
    {
        $connection = OrderReturn::query()->getConnection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('An order\'s returns must be locked inside a database transaction.');
        }

        $connection->select('select pg_advisory_xact_lock(hashtext(?))', ['returns:order:'.$order->id]);
    }

    /**
     * The return as saved now, locked until the transaction ends.
     *
     * @throws LogicException When called outside a transaction.
     */
    public function lock(OrderReturn $return): OrderReturn
    {
        if (OrderReturn::query()->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Returns must be locked inside a database transaction.');
        }

        return OrderReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Moves a locked return to the next status, stamping when, and records the change.
     *
     * @param  array<string, mixed>  $fields  Anything else to save with it, such as a rejection reason.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    public function move(OrderReturn $locked, ReturnStatus $next, ?AccountReference $by, array $fields = []): OrderReturn
    {
        if (! $locked->status->canMoveTo($next)) {
            throw new ReturnTransitionNotAllowedException;
        }

        $from = $locked->status;
        $locked->forceFill([...$fields, 'status' => $next, self::stampFor($next) => CarbonImmutable::now()])->save();
        $this->record($locked, $from, $next, $by);

        return $locked;
    }

    /**
     * Adds a change to the return's history, and announces it once the transaction commits (for Messaging, Step 20).
     */
    public function record(OrderReturn $return, ?ReturnStatus $from, ReturnStatus $to, ?AccountReference $by): void
    {
        (new ReturnStatusChange)->forceFill([
            'return_id' => $return->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by_type' => $by?->type,
            'changed_by_id' => $by?->publicId,
        ])->save();

        match ($to) {
            ReturnStatus::Requested => ReturnRequested::dispatch($return),
            ReturnStatus::Approved => ReturnApproved::dispatch($return),
            ReturnStatus::Rejected => ReturnRejected::dispatch($return),
            ReturnStatus::Received => ReturnReceived::dispatch($return),
            ReturnStatus::Inspected => ReturnInspected::dispatch($return),
            ReturnStatus::Completed => ReturnCompleted::dispatch($return),
            ReturnStatus::Cancelled => ReturnCancelled::dispatch($return),
        };
    }

    private static function stampFor(ReturnStatus $status): string
    {
        return match ($status) {
            ReturnStatus::Requested => 'requested_at',
            ReturnStatus::Approved => 'approved_at',
            ReturnStatus::Rejected => 'rejected_at',
            ReturnStatus::Received => 'received_at',
            ReturnStatus::Inspected => 'inspected_at',
            ReturnStatus::Completed => 'completed_at',
            ReturnStatus::Cancelled => 'cancelled_at',
        };
    }
}
