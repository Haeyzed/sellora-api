<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Services;

use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\ShipmentLine;
use App\Tenant\Payments\Actions\FindRefundedUnits;
use App\Tenant\Returns\Data\ReturnableLine;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Models\ReturnLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What can still be returned of each of an order's lines (section 13): its delivered units (within the window, for buyers), less units in returns that aren't rejected or cancelled, less units refunded by line outside a return.
 *
 * Read under the order's returns lock (ReturnLocks), so two requests at once
 * never claim the same units. A window of 0 days means buyers can't ask at
 * all, and staff need no reason for how long ago a line was delivered.
 */
final readonly class ReturnableUnits
{
    public function __construct(private FindRefundedUnits $findRefundedUnits) {}

    /**
     * @param  list<string>  $returnRefunds  Public IDs of the refunds returns made, whose units their returns already count.
     * @return array<string, ReturnableLine> By order line public ID, in the order's line order.
     */
    public function of(Order $order, int $windowDays, CarbonImmutable $now, array $returnRefunds = []): array
    {
        $lines = OrderLine::query()->where('order_id', $order->id)->orderBy('position')->get();
        $since = $now->subDays($windowDays);
        $shipped = $this->shippedUnits($order, $since);
        $returned = ReturnLine::query()
            ->whereHas('orderReturn', static fn (Builder $returns) => $returns->where('order_id', $order->id)->whereIn('status', ReturnStatus::holdingUnits()))
            ->groupBy('order_line_id')
            ->selectRaw('order_line_id, sum(quantity) as units')
            ->pluck('units', 'order_line_id')
            ->map(static fn (mixed $units): int => (int) $units)
            ->all();
        $refunded = $this->findRefundedUnits->handle($order, $returnRefunds);
        $returnable = [];

        foreach ($lines as $line) {
            $units = $shipped[$line->id] ?? ['delivered' => 0, 'in_window' => 0, 'shipped' => 0];
            $returnable[$line->public_id] = new ReturnableLine(
                $line,
                $units['delivered'],
                $units['in_window'],
                $units['shipped'],
                ($returned[$line->id] ?? 0) + ($refunded[$line->public_id] ?? 0),
                $windowDays > 0,
            );
        }

        return $returnable;
    }

    /**
     * Units of each line in delivered shipments (and of those, delivered since the window opened) and in shipments shipped but never marked delivered.
     *
     * @return array<int, array{delivered: int, in_window: int, shipped: int}> By order line ID.
     */
    private function shippedUnits(Order $order, CarbonImmutable $since): array
    {
        $units = [];
        $rows = ShipmentLine::query()
            ->join('shipments', 'shipments.id', '=', 'shipment_lines.shipment_id')
            ->where('shipments.order_id', $order->id)
            ->whereIn('shipments.status', [ShipmentStatus::Delivered->value, ShipmentStatus::Shipped->value])
            ->get(['shipment_lines.order_line_id', 'shipment_lines.quantity', 'shipments.status', 'shipments.delivered_at']);

        foreach ($rows as $row) {
            $line = $row->order_line_id;
            $units[$line] ??= ['delivered' => 0, 'in_window' => 0, 'shipped' => 0];
            $deliveredAt = $row->getAttribute('delivered_at');

            if ($row->getAttribute('status') === ShipmentStatus::Delivered->value) {
                $units[$line]['delivered'] += $row->quantity;
                $units[$line]['in_window'] += $deliveredAt !== null && CarbonImmutable::parse((string) $deliveredAt)->greaterThanOrEqualTo($since) ? $row->quantity : 0;
            } else {
                $units[$line]['shipped'] += $row->quantity;
            }
        }

        return $units;
    }
}
