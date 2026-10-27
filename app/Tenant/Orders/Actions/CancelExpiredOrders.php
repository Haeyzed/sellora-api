<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Orders\Contracts\PaymentCheckBeforeExpiry;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\OrderProgress;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cancels unpaid orders whose payment deadline has passed, a batch at a time, oldest deadline first, for the scheduled sweep. Safe to run twice and alongside payments.
 *
 * An order paid on delivery stops expiring once its first shipment exists,
 * and starts again, with a fresh deadline, if its delivery comes back.
 * A gateway payment still under way is asked about one last time first,
 * outside any transaction. Each order is then cancelled in its own short
 * transaction, and only if it is still open and unpaid once locked: a
 * payment that arrived meanwhile wins.
 */
final readonly class CancelExpiredOrders
{
    public function __construct(
        private CancelOrder $cancelOrder,
        private PaymentCheckBeforeExpiry $paymentCheckBeforeExpiry,
    ) {}

    /**
     * @return int How many orders were cancelled.
     */
    public function handle(int $limit): int
    {
        $cancelled = 0;
        $candidates = Order::query()
            ->where('status', OrderStatus::Open->value)
            ->whereIn('payment_status', [OrderPaymentStatus::Unpaid->value, OrderPaymentStatus::PartiallyPaid->value])
            // The deadline runs again from a delivery that came back (Step 17), as OrderProgress::paymentDeadline() works out.
            ->whereRaw('coalesce((select max(shipments.returned_at) from shipments where shipments.order_id = orders.id) + (orders.payment_due_at - orders.placed_at), orders.payment_due_at) <= ?', [now()])
            // Paid on delivery and on its way: it no longer expires, so it must not hold up the batch.
            ->where(static fn (Builder $expiring) => $expiring
                ->where('pay_on_delivery', false)
                ->orWhereDoesntHave('shipments', static fn (Builder $shipments) => $shipments->whereNotIn('status', OrderProgress::endedWithoutDelivery())))
            ->orderBy('payment_due_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($candidates as $order) {
            $this->paymentCheckBeforeExpiry->checkBeforeExpiry($order);
            $cancelled += $this->cancelOrder->expire($order) ? 1 : 0;
        }

        return $cancelled;
    }
}
