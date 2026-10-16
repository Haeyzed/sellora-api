<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Actions\ReleaseReservation;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Enums\FulfilmentStatus;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Enums\PaymentStatus;
use App\Tenant\Orders\Events\OrderCancelled;
use App\Tenant\Orders\Exceptions\OrderAlreadyPaidException;
use App\Tenant\Orders\Exceptions\OrderCannotBeCancelledException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\OrderActivity;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use App\Tenant\Promotions\Actions\SettlePromotionUses;
use App\Tenant\Promotions\Data\RedemptionHolder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an open order before anything has shipped, giving back the stock it holds and its promotion uses (section 13).
 *
 * Staff may cancel a paid order too: it is then marked as owing a refund,
 * which Payments makes (Step 15). Its customer may cancel only before paying.
 * Unpaid orders are also cancelled when their payment deadline passes
 * (expire()). Once anything has shipped, returns (Step 18) take over.
 */
final readonly class CancelOrder
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
        private ReleaseReservation $releaseReservation,
        private SettlePromotionUses $settlePromotionUses,
        private OrderActivity $orderActivity,
    ) {}

    /**
     * Staff cancelling an order, recorded in the activity log.
     *
     * @throws OrderCannotBeCancelledException When it is completed, cancelled, or has started to ship.
     */
    public function byStaff(StaffMember $actor, Order $order): Order
    {
        $cancelled = $this->handle($order, CancellationReason::Staff, AccountReference::to($actor));
        $this->orderActivity->record($actor, $cancelled, 'order_cancelled', 'Cancelled');

        return $cancelled;
    }

    /**
     * Staff, or the order's customer, cancelling it.
     *
     * @throws OrderCannotBeCancelledException When it is completed, cancelled, or has started to ship.
     * @throws OrderAlreadyPaidException When its customer cancels it after paying.
     */
    public function handle(Order $order, CancellationReason $reason, AccountReference $actor): Order
    {
        return DB::transaction(function () use ($order, $reason, $actor): Order {
            $locked = $this->orderProgress->lock($order);
            $this->ensureCancellable($locked, $reason);

            return $this->cancel($locked, $reason, $actor);
        });
    }

    /**
     * Cancels an order whose payment deadline has passed, if it is still open and unpaid once locked: a payment may have arrived meanwhile. Says whether it did.
     */
    public function expire(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $locked = $this->orderProgress->lock($order);

            if (! $locked->awaitsPayment() || $locked->payment_due_at->isFuture()) {
                return false;
            }

            $this->cancel($locked, CancellationReason::PaymentExpired, null);

            return true;
        });
    }

    /**
     * @throws OrderCannotBeCancelledException
     * @throws OrderAlreadyPaidException
     */
    private function ensureCancellable(Order $locked, CancellationReason $reason): void
    {
        if ($locked->status !== OrderStatus::Open || $this->hasShipped($locked)) {
            throw new OrderCannotBeCancelledException;
        }

        if ($reason === CancellationReason::Customer && $locked->payment_status === PaymentStatus::Paid) {
            throw new OrderAlreadyPaidException;
        }
    }

    /**
     * Whether anything on it has left: then it can't be cancelled.
     */
    private function hasShipped(Order $locked): bool
    {
        return in_array($locked->fulfilment_status, [FulfilmentStatus::PartiallyFulfilled, FulfilmentStatus::Fulfilled], true);
    }

    private function cancel(Order $locked, CancellationReason $reason, ?AccountReference $actor): Order
    {
        $this->releaseReservation->handle(StockHolder::of($locked), causer: $actor);
        $this->settlePromotionUses->release(new RedemptionHolder($locked->getMorphClass(), $locked->public_id));

        $locked->forceFill([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => CarbonImmutable::now(),
            'cancellation_reason' => $reason,
            'cancelled_by_type' => $actor?->type,
            'cancelled_by_id' => $actor?->publicId,
            'refund_due' => $locked->payment_status === PaymentStatus::Paid,
        ])->save();

        $this->orderEventLog->record($locked, OrderEventType::Cancelled, ['from' => OrderStatus::Open->value, 'to' => OrderStatus::Cancelled->value, 'reason' => $reason->value, 'refund_due' => $locked->refund_due], $actor);
        OrderCancelled::dispatch($locked);

        return $locked;
    }
}
