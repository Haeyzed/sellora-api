<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Inventory\Actions\CommitReservedStock;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Events\OrderPaid;
use App\Tenant\Orders\Events\OrderPaidAfterCancellation;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use App\Tenant\Promotions\Actions\SettlePromotionUses;
use App\Tenant\Promotions\Data\RedemptionHolder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records that an order was paid for, for Payments to call once a payment is confirmed with the gateway (Step 15). Safe to run twice.
 *
 * An open order becomes paid: its promotion uses are committed, the lines
 * that don't ship are fulfilled and their stock leaves the shelf, and it
 * completes when nothing is left to send. A payment for an order already
 * cancelled (expired, or cancelled meanwhile) never revives it: the order
 * stays cancelled, is marked as owing a refund, and staff are alerted.
 */
final readonly class MarkOrderAsPaid
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
        private SettlePromotionUses $settlePromotionUses,
        private CommitReservedStock $commitReservedStock,
    ) {}

    public function handle(Order $order, ?CarbonImmutable $paidAt = null): Order
    {
        return DB::transaction(function () use ($order, $paidAt): Order {
            $locked = $this->orderProgress->lock($order);

            if ($locked->payment_status->wasPaidInFull()) {
                return $locked;
            }

            $from = $locked->payment_status;
            $locked->forceFill(['payment_status' => OrderPaymentStatus::Paid, 'paid_at' => $paidAt ?? CarbonImmutable::now()]);

            if ($locked->status === OrderStatus::Cancelled) {
                return $this->owedBack($locked);
            }

            $locked->save();
            $holder = new RedemptionHolder($locked->getMorphClass(), $locked->public_id);
            $this->settlePromotionUses->commit($holder, $holder);
            $this->commitLinesThatDontShip($locked);
            $this->orderEventLog->record($locked, OrderEventType::Paid, ['from' => $from->value, 'to' => OrderPaymentStatus::Paid->value]);
            OrderPaid::dispatch($locked);
            $this->orderProgress->completeIfDone($locked);

            return $locked;
        });
    }

    /**
     * The order was cancelled before the payment arrived: it stays cancelled, and the payment is owed back.
     */
    private function owedBack(Order $locked): Order
    {
        $locked->forceFill(['refund_due' => true])->save();
        $this->orderEventLog->record($locked, OrderEventType::PaidAfterCancellation, ['reason' => $locked->cancellation_reason?->value]);
        // No contact details: the order's public ID is enough for staff to find it.
        Log::warning('A payment arrived for a cancelled order; it stays cancelled and the payment is owed back.', ['order' => $locked->public_id]);
        OrderPaidAfterCancellation::dispatch($locked);

        return $locked;
    }

    /**
     * Lines that don't ship are fulfilled once paid, so their held stock leaves the shelf now. A part on backorder that hasn't arrived stays held, and is logged for staff.
     */
    private function commitLinesThatDontShip(Order $locked): void
    {
        $quantities = [];

        foreach ($locked->lines()->where('requires_shipping', false)->get() as $line) {
            /** @var OrderLine $line */
            $quantities[$line->product_variant_id] = ($quantities[$line->product_variant_id] ?? 0) + $line->quantity;
        }

        try {
            DB::transaction(fn (): int => $this->commitReservedStock->handle(StockHolder::of($locked), $quantities));
        } catch (InsufficientStockException) {
            Log::warning('Stock for paid lines that don\'t ship isn\'t on hand yet; it stays held.', ['order' => $locked->public_id]);
        }
    }
}
