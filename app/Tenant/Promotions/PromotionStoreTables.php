<?php

declare(strict_types=1);

namespace App\Tenant\Promotions;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How promotions appear in a store export: all of their setup. It holds no personal data and no secrets.
 */
final class PromotionStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('promotions', include: [
            'id', 'public_id', 'name', 'title', 'method', 'type', 'applies_to', 'percent', 'currency', 'discount_amount', 'max_discount_amount',
            'min_subtotal_amount', 'min_quantity', 'buy_quantity', 'get_quantity', 'max_applications', 'includes_sale_items', 'first_order_only',
            'combines_with_product_discounts', 'combines_with_order_discounts', 'combines_with_shipping_discounts', 'usage_limit',
            'usage_limit_per_customer', 'starts_at', 'ends_at', 'is_active', 'created_at', 'updated_at', 'deleted_at',
        ]);
        $registry->table('promotion_currency_amounts', include: ['id', 'promotion_id', 'currency_code', 'discount_amount', 'max_discount_amount', 'min_subtotal_amount', 'created_at', 'updated_at']);
        $registry->table('promotion_products', include: ['promotion_id', 'product_id', 'list']);
        $registry->table('promotion_variants', include: ['promotion_id', 'product_variant_id', 'list']);
        $registry->table('promotion_categories', include: ['promotion_id', 'category_id', 'list']);
        $registry->table('promotion_customer_groups', include: ['promotion_id', 'customer_group_id']);
    }
}
