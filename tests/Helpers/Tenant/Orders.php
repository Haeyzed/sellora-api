<?php

declare(strict_types=1);

use App\Tenant\Shipping\Enums\ShippingFulfilment;
use App\Tenant\Shipping\Enums\ShippingPricing;
use App\Tenant\Shipping\Models\ShippingMethod;
use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| For the tests in tests/Feature/Tenant/Orders
|--------------------------------------------------------------------------
*/

/**
 * Writes an order's rows straight into the current store's database, with one line, its discount and tax, a shipping line and a shipping address, and returns the order's ID. For testing what the database allows; real orders are placed with PlaceOrder.
 *
 * @param  array<string, mixed>  $order  Columns to change on the order.
 */
function rawOrder(array $order = []): int
{
    $variant = cartVariant(5000);
    $zone = ShippingZone::query()->firstOrCreate(['name' => 'Everywhere'], ['is_active' => true, 'covers_rest_of_world' => false]);
    $method = ShippingMethod::query()->first() ?? tap((new ShippingMethod)->forceFill([
        'shipping_zone_id' => $zone->id, 'name' => ['en' => 'Standard'], 'fulfilment' => ShippingFulfilment::Ship, 'pricing' => ShippingPricing::Flat, 'currency' => 'NGN',
    ]))->save();
    $now = now();

    $orderId = DB::table('orders')->insertGetId([
        'public_id' => strtolower((string) Str::ulid()), 'checkout_id' => strtolower((string) Str::ulid()), 'fulfilment_status' => 'unfulfilled',
        'contact_email' => 'ada@example.com', 'contact_name' => 'Ada Obi', 'contact_phone' => '+2348012345678', 'locale' => 'en',
        'currency' => 'NGN', 'base_currency' => 'NGN', 'exchange_rate' => '1', 'rate_source' => 'base', 'tax_mode' => 'inclusive',
        'tax_calculated_by' => 'rate_table', 'tax_country_code' => 'NG', 'subtotal_amount' => 10000, 'discount_amount' => 1000,
        'shipping_amount' => 1500, 'shipping_discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 10500, 'base_total_amount' => 10500,
        'customer_note' => 'Leave it at the gate.', 'placed_at' => $now, 'payment_due_at' => $now->copy()->addHour(), 'created_at' => $now, 'updated_at' => $now,
        ...$order,
    ]);
    $lineId = DB::table('order_lines')->insertGetId([
        'public_id' => strtolower((string) Str::ulid()), 'order_id' => $orderId, 'position' => 0, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id,
        'product_name' => 'Linen shirt', 'sku' => $variant->sku, 'requires_shipping' => true, 'weight_grams' => 500, 'tax_class_name' => 'Standard', 'tax_outcome' => 'no_rate',
        'quantity' => 2, 'currency' => 'NGN', 'unit_price_amount' => 5000, 'price_is_fixed' => false, 'subtotal_amount' => 10000, 'discount_amount' => 1000,
        'net_amount' => 9000, 'tax_amount' => 0, 'total_amount' => 9000, 'created_at' => $now,
    ]);
    DB::table('order_discounts')->insert(['order_id' => $orderId, 'order_line_id' => $lineId, 'source' => 'promotion', 'reference' => 'promo', 'label' => '10% off', 'class' => 'order', 'currency' => 'NGN', 'discount_amount' => 1000, 'created_at' => $now]);
    DB::table('order_taxes')->insert(['order_id' => $orderId, 'order_line_id' => $lineId, 'name' => 'VAT', 'country_code' => 'NG', 'rate_percent' => '0', 'currency' => 'NGN', 'tax_amount' => 0, 'created_at' => $now]);
    DB::table('order_shipping')->insert([
        'order_id' => $orderId, 'shipping_method_id' => $method->id, 'method_name' => 'Standard', 'fulfilment' => 'ship', 'currency' => 'NGN',
        'price_amount' => 1500, 'discount_amount' => 0, 'net_amount' => 1500, 'tax_amount' => 0, 'total_amount' => 1500, 'created_at' => $now,
    ]);
    DB::table('order_addresses')->insert([
        'order_id' => $orderId, 'kind' => 'shipping', 'version' => 1, 'recipient_name' => 'Ada Obi', 'line1' => '5 Allen Avenue', 'city' => 'Ikeja', 'state_code' => 'LA',
        'country_code' => 'NG', 'phone' => '+2348012345678', 'created_at' => $now,
    ]);
    DB::table('order_events')->insert(['order_id' => $orderId, 'type' => 'placed', 'created_at' => $now]);

    return $orderId;
}
