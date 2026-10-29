<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Privacy;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Services\ReturnPhotos;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a buyer's returns say about them, for their data export: each return of the orders given, as it stands.
 *
 * Erasure needs nothing here: returns lose their note and photos when their
 * order is anonymised (ForgetReturnsWhenOrderAnonymised), which the orders'
 * own handler does once each order settles.
 */
final class ReturnPrivacy
{
    /**
     * @param  Builder<Order>  $orders
     * @return list<array<string, mixed>>
     */
    public function export(Builder $orders): array
    {
        return array_values(OrderReturn::query()
            ->whereIn('order_id', $orders->select('id'))
            ->with(['order', 'lines.orderLine', 'media'])
            ->orderBy('id')
            ->get()
            ->map(static fn (OrderReturn $return): array => [
                'number' => $return->displayNumber(),
                'order' => $return->order->number,
                'status' => $return->status->value,
                'reason' => $return->reason->value,
                'note' => $return->note,
                'rejection_reason' => $return->rejection_reason,
                'lines' => array_values($return->lines->map(static fn (ReturnLine $line): array => [
                    'product' => $line->orderLine->product_name,
                    'variant' => $line->orderLine->variant_name,
                    'quantity' => $line->quantity,
                ])->all()),
                'photos' => $return->getMedia(ReturnPhotos::COLLECTION)->count(),
                'requested_at' => $return->requested_at->toIso8601String(),
                'completed_at' => $return->completed_at?->toIso8601String(),
            ])
            ->all());
    }
}
