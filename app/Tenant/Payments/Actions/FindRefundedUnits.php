<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\RefundLine;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many units of each of an order's lines were given back by refunds of lines, for Returns (Step 18) to work out what can still be returned.
 *
 * Counted as RefundAmounts counts them: a refund's units count while any part
 * of it holds money (pending or succeeded), and a refund that failed entirely
 * frees them again. Returns leaves out the refunds it made itself, whose
 * units it already counts in its own returns.
 */
final readonly class FindRefundedUnits
{
    /**
     * @param  list<string>  $exceptRefunds  Public IDs of refunds to leave out.
     * @return array<string, int> Units given back, by order line public ID; lines with none are left out.
     */
    public function handle(Order $order, array $exceptRefunds = []): array
    {
        /** @var array<string, int> $units */
        $units = RefundLine::query()
            ->join('order_lines', 'order_lines.id', '=', 'refund_lines.order_line_id')
            ->where('order_lines.order_id', $order->id)
            ->whereHas('refund', static fn (Builder $refunds) => $refunds->whereNotIn('public_id', $exceptRefunds))
            ->whereHas('refund.paymentRefunds', static fn (Builder $parts) => $parts->whereIn('status', RefundStatus::holdingMoney()))
            ->groupBy('order_lines.public_id')
            ->selectRaw('order_lines.public_id as line, sum(refund_lines.quantity) as units')
            ->pluck('units', 'line')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return $units;
    }
}
