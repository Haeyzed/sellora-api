<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How orders appear in a store export: all of them. They are the store's own financial records, buyers' contact details and addresses included, as for customers.
 */
final class OrderStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table('orders', include: [
            'id', 'public_id', 'number', 'checkout_id', 'status', 'payment_status', 'fulfilment_status', 'refund_due', 'is_test', 'customer_id',
            'contact_email', 'contact_name', 'contact_phone', 'locale', 'currency', 'base_currency', 'exchange_rate', 'market_rate', 'margin_percent',
            'rate_source', 'rate_as_of', 'tax_mode', 'tax_calculated_by', 'tax_country_code', 'tax_state_code', 'tax_postal_code',
            'subtotal_amount', 'discount_amount', 'shipping_amount', 'shipping_discount_amount', 'tax_amount', 'total_amount', 'base_total_amount',
            'customer_note', 'placed_at', 'payment_due_at', 'paid_at', 'fulfilled_at', 'completed_at', 'cancelled_at', 'cancellation_reason',
            'cancelled_by_type', 'cancelled_by_id', 'anonymise_when_settled', 'anonymised_at', 'created_at', 'updated_at',
        ]);
        $registry->table('order_lines', include: [
            'id', 'public_id', 'order_id', 'position', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'sku', 'requires_shipping',
            'weight_grams', 'tax_class_id', 'tax_class_name', 'tax_outcome', 'quantity', 'currency', 'unit_price_amount', 'price_is_fixed',
            'subtotal_amount', 'discount_amount', 'net_amount', 'tax_amount', 'total_amount', 'created_at',
        ]);
        $registry->table('order_discounts', include: ['id', 'order_id', 'order_line_id', 'source', 'reference', 'promotion_id', 'code', 'label', 'class', 'currency', 'discount_amount', 'created_at']);
        $registry->table('order_taxes', include: ['id', 'order_id', 'order_line_id', 'name', 'country_code', 'state_code', 'rate_percent', 'currency', 'tax_amount', 'created_at']);
        $registry->table('order_shipping', include: [
            'id', 'order_id', 'shipping_method_id', 'method_name', 'fulfilment', 'carrier', 'carrier_service', 'pickup_country_code', 'pickup_state_code',
            'pickup_address_line1', 'pickup_city', 'pickup_postal_code', 'currency', 'price_amount', 'discount_amount', 'net_amount', 'tax_amount',
            'total_amount', 'tax_outcome', 'min_days', 'max_days', 'earliest_on', 'latest_on', 'created_at',
        ]);
        $registry->table('order_addresses', include: [
            'id', 'order_id', 'kind', 'version', 'recipient_name', 'company', 'line1', 'line2', 'city', 'state_code', 'postal_code', 'country_code', 'phone',
            'created_by_type', 'created_by_id', 'created_at',
        ]);
        $registry->table('order_events', include: ['id', 'order_id', 'type', 'data', 'actor_type', 'actor_id', 'created_at']);
        $registry->table('order_contact_corrections', include: ['id', 'order_id', 'email', 'name', 'phone', 'created_by_type', 'created_by_id', 'created_at']);
        $registry->table('order_notes', include: ['id', 'public_id', 'order_id', 'author_staff_id', 'body', 'created_at']);
        $registry->table('order_tags', include: ['order_id', 'tag']);
        $registry->table('shipments', include: [
            'id', 'public_id', 'order_id', 'fulfilment', 'stock_location_id', 'status', 'carrier', 'carrier_service', 'tracking_number', 'tracking_url',
            'ready_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'created_by_type', 'created_by_id', 'created_at', 'updated_at',
        ]);
        $registry->table('shipment_lines', include: ['id', 'shipment_id', 'order_line_id', 'quantity']);
    }
}
