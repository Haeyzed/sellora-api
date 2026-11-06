<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\OrderLine;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which products sold the most units recently, for the storefront's best sellers: counted from orders that were paid, never from test orders or cancelled ones.
 *
 * For other domains to call (section 6). Units are gross: what was sold,
 * before any return or refund, over the last orders.best_sellers_period_days
 * (30) by when each order was placed. An order counts once paid in full,
 * and keeps counting after a later refund; an unpaid or partly paid order
 * never counts. Reads orders only, never changes them.
 */
final readonly class FindBestSellingProducts
{
    /**
     * @param  Builder<Product>|null  $amongProducts  Only these products, such as a category's; null for all.
     * @return list<int> Product IDs, the most units first; equal units go to the product sold first.
     */
    public function handle(int $limit, ?Builder $amongProducts = null): array
    {
        $query = OrderLine::query()
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('orders.is_test', false)
            ->where('orders.status', '<>', OrderStatus::Cancelled->value)
            ->whereIn('orders.payment_status', [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value, OrderPaymentStatus::Refunded->value])
            ->where('orders.placed_at', '>=', now()->subDays(config()->integer('orders.best_sellers_period_days')))
            ->groupBy('order_lines.product_id')
            ->orderByRaw('sum(order_lines.quantity) desc')
            ->orderByRaw('min(order_lines.id)')
            ->limit($limit);

        if ($amongProducts !== null) {
            $query->whereIn('order_lines.product_id', $amongProducts->select('products.id'));
        }

        return array_values(array_map(intval(...), $query->pluck('order_lines.product_id')->all()));
    }
}
