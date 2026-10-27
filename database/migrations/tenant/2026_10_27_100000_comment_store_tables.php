<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Describes every store table, and the columns whose meaning isn't clear
 * from their name, in the store's database itself.
 *
 * Only comments change. Table comments go through Laravel; a column comment
 * can only be set through ->change(), which restates the column's type and
 * drops any modifier not repeated, so columns use PostgreSQL's COMMENT ON,
 * built from the constants below only. Columns that already had a comment
 * keep it and are not listed here. Amounts are in minor units of the
 * currency named beside them (cents, kobo), unless a comment says otherwise.
 */
return new class extends Migration
{
    private const string MINOR_UNITS = 'In minor units of the row\'s currency.';

    private const string BASE_MINOR_UNITS = 'In minor units of the store\'s base currency.';

    private const string PUBLIC_ID = 'Public ID (ULID) used in the API; the numeric id never leaves the database.';

    /**
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    private const array TABLES = [
        'activity_log' => ['The store\'s activity log (spatie/laravel-activitylog): what staff, customers and the system did.', [
            'causer_type' => 'Morph name of the account that did it; null for the system.',
        ]],
        'attribute_product' => ['Which options (attributes) a product uses, in display order.', []],
        'attribute_value_product_variant' => ['The value a variant has for each of its product\'s options. The database checks the value belongs to that option, and one value per option.', []],
        'attribute_values' => ['Values of a product option, such as M or Blue.', [
            'public_id' => self::PUBLIC_ID,
            'label' => 'Translated label, by language code.',
        ]],
        'attributes' => ['Product options, such as Size or Colour.', [
            'public_id' => self::PUBLIC_ID,
            'name' => 'Translated name, by language code.',
        ]],
        'audits' => ['Before-and-after changes to audited store records (owen-it/laravel-auditing).', [
            'user_type' => 'Morph name of the account that made the change; null for the system.',
        ]],
        'brands' => ['Product brands. Soft-deleted, so old references stay intact.', [
            'public_id' => self::PUBLIC_ID,
            'name' => 'Translated name, by language code.',
            'slug' => 'Readable URL identifier, unique among brands not in the trash; not changed by later edits.',
            'description' => 'Translated description, by language code.',
        ]],
        'cart_lines' => ['One variant and quantity in a cart; a variant appears once per cart.', [
            'public_id' => self::PUBLIC_ID,
            'quantity' => 'Whole units, at least 1 and within the configured per-line limit.',
            'seen_price_amount' => 'Unit price the shopper last saw, in minor units of seen_price_currency; for telling them a price changed, never charged.',
            'seen_price_source' => 'One of App\Tenant\Cart\Enums\SeenPriceSource.',
        ]],
        'carts' => ['A shopper\'s cart, before an order exists. Holds no stock and guarantees no price. Belongs to a customer or a guest, never both. Inactive carts are removed by the retention policy (guests\' sooner).', [
            'public_id' => self::PUBLIC_ID,
            'customer_id' => 'The signed-in customer\'s cart; unique, so one cart per customer. Null for a guest.',
            'token_hash' => 'SHA-256 of a guest\'s cart token; the token itself is never stored.',
            'currency' => 'Currency the shopper chose; null means the store\'s base currency.',
            'promotion_code' => 'The code entered; kept even while the basket doesn\'t meet its conditions.',
            'version' => 'Goes up with every change; a client sending an older version is refused.',
            'last_activity_at' => 'Inactive carts are removed by the retention policy, counted from here.',
        ]],
        'categories' => ['Product categories, as a tree. Soft-deleted, so old references stay intact.', [
            'public_id' => self::PUBLIC_ID,
            'name' => 'Translated name, by language code.',
            'slug' => 'Readable URL identifier, unique among categories not in the trash; not changed by later edits.',
            'description' => 'Translated description, by language code.',
        ]],
        'category_product' => ['Categories each product is in.', []],
        'checkout_payment_holds' => ['Each payment method\'s share of a placed order, from its checkout.', [
            'public_id' => self::PUBLIC_ID,
            'status' => 'One of App\Tenant\Checkout\Enums\PaymentHoldStatus.',
        ]],
        'checkouts' => ['A cart\'s checkout: the buyer\'s details and a short-lived quote. At most one open per cart; holds no stock until placed.', [
            'public_id' => self::PUBLIC_ID,
            'status' => 'One of App\Tenant\Checkout\Enums\CheckoutStatus.',
            'shipping_address' => 'Copy of the address, not a link to a saved one.',
            'buyer_tax_id_country' => 'ISO 3166-1 alpha-2 country that issued buyer_tax_id; always set with it.',
            'locale' => 'Language names and documents are given in.',
            'quote_version' => 'Goes up with every new quote; placing checks the buyer confirmed the latest.',
            'quote_expires_at' => 'After this the quote must be refreshed before placing.',
            'last_activity_at' => 'Inactive checkouts are removed by the retention policy, counted from here.',
        ]],
        'credit_note_lines' => ['Lines of a credit note, copied when it was issued. Never changed.', [
            'kind' => 'One of App\Tenant\Orders\Enums\CreditNoteLineKind.',
            'net_amount' => self::MINOR_UNITS,
            'tax_amount' => self::MINOR_UNITS,
            'total_amount' => self::MINOR_UNITS,
        ]],
        'credit_note_taxes' => ['Taxes credited by a credit note, by line, copied when it was issued.', [
            'rate_percent' => 'A percentage: 7.5 means 7.5%.',
            'tax_amount' => self::MINOR_UNITS,
        ]],
        'credit_notes' => ['Credit notes: tax documents for a refund that reduces a sale, one per refund part. Copies the seller, buyer and amounts when issued; never changed, except that buyer details are erased on request.', [
            'public_id' => self::PUBLIC_ID,
            'series' => 'The year, or "all" when numbered continuously; null for a test order.',
            'sequence_number' => 'Number within the series, without gaps.',
            'tax_mode' => 'One of App\Shared\Money\TaxMode.',
            'exchange_rate' => 'Base amount × rate = amount in its currency.',
            'buyer_erased_at' => 'When the buyer\'s personal details were erased; amounts and numbering stay.',
            'net_amount' => self::MINOR_UNITS,
            'tax_amount' => self::MINOR_UNITS,
            'pdf_path' => 'Where its PDF is stored, privately; null until rendered.',
        ]],
        'customer_addresses' => ['Customers\' saved addresses. Orders copy the address they use, so editing one never changes a past order.', [
            'public_id' => self::PUBLIC_ID,
            'country_code' => 'ISO 3166-1 alpha-2.',
            'phone' => 'E.164.',
        ]],
        'customer_data_exports' => ['Data exports customers asked for, built in the background and removed after expires_at.', [
            'public_id' => self::PUBLIC_ID,
            'status' => 'One of App\Tenant\Customers\Enums\CustomerDataExportStatus.',
            'path' => 'Private file path on the disk; never a public URL.',
        ]],
        'customer_email_tokens' => ['Emailed one-time links for customers: one pending per customer and purpose, replaced by a new one.', [
            'purpose' => 'One of App\Tenant\Customers\Enums\CustomerEmailTokenPurpose.',
            'email' => 'Address the link was sent to; for an email change, the new address.',
            'token_hash' => 'SHA-256 of the link\'s token; the token itself is never stored.',
        ]],
        'customer_group_members' => ['Customers in each group, assigned by staff.', []],
        'customer_groups' => ['Named groups of customers, such as Wholesale, managed by hand; promotions can target them.', [
            'public_id' => self::PUBLIC_ID,
        ]],
        'customer_identities' => ['Sign-in providers linked to a customer account, by the provider\'s own user ID. Provider access tokens are not kept.', [
            'public_id' => self::PUBLIC_ID,
            'provider' => 'One of App\Tenant\Customers\Enums\SocialProvider.',
        ]],
        'customer_notes' => ['Staff-only notes about a customer; never shown to the customer.', [
            'public_id' => self::PUBLIC_ID,
        ]],
        'customer_password_reset_tokens' => ['Pending password resets for customers (Laravel password broker).', [
            'token' => 'Hash of the reset token; the token itself is never stored.',
        ]],
        'customers' => ['Shopper accounts in this store. Erasing one clears its personal details and keeps the row, so past orders still point to it.', [
            'public_id' => self::PUBLIC_ID,
            'email' => 'Lower case; the sign-in name. Null once erased.',
            'password' => 'Password hash; null for an account that only signs in through a provider.',
            'is_active' => 'False blocks sign-in and rejects existing tokens.',
            'phone' => 'E.164.',
            'locale' => 'Preferred language for messages.',
            'search_text' => 'Lower-case copy of the name, email and phone, for staff search.',
            'is_tax_exempt' => 'Set by staff after checking the tax ID.',
            'tax_id_country' => 'ISO 3166-1 alpha-2 country that issued tax_id. The ID is checked for format only.',
            'erased_at' => 'When personal details were erased.',
        ]],
        'document_sequences' => ['Last number used for invoices and credit notes in each series, so numbers have no gaps.', []],
        'drivers' => ['The store\'s own delivery drivers, who sign in to the driver app with phone and PIN.', [
            'public_id' => self::PUBLIC_ID,
            'is_active' => 'False blocks sign-in and rejects existing tokens.',
        ]],
        'fixed_prices' => ['A variant\'s exact price in one of the store\'s other currencies, used instead of converting the base price.', [
            'currency_code' => 'ISO 4217.',
            'price_amount' => 'In minor units of currency_code.',
            'compare_at_price_amount' => 'Former price shown beside it, in minor units of currency_code; higher than price_amount.',
        ]],
        'idempotency_keys' => ['Idempotency-Key requests on this store: a retried request gets the first answer instead of running twice. Removed after expires_at.', [
            'scope' => 'Who sent the key (account or client), so keys never clash between callers.',
            'request_hash' => 'SHA-256 of the request, so a key reused with a different request is refused.',
            'status' => 'One of App\Shared\Idempotency\IdempotencyStatus.',
            'response_body' => 'The first answer, encrypted with the application key.',
        ]],
        'inventory_items' => ['Stock settings for a variant, whatever the location. A variant without one uses the defaults.', [
            'tracks_stock' => 'False: always available and never reserved.',
            'allows_backorder' => 'Holds may exceed stock on hand.',
            'low_stock_threshold' => 'Warning level; null uses store_settings.low_stock_threshold.',
            'unit_cost' => 'Current cost of one unit in the base currency, as a decimal (not minor units); staff only.',
        ]],
        'invoice_lines' => ['Lines of an invoice, copied when it was issued. Never changed.', [
            'kind' => 'One of App\Tenant\Orders\Enums\InvoiceLineKind.',
            'unit_price_amount' => self::MINOR_UNITS,
            'subtotal_amount' => self::MINOR_UNITS,
            'discount_amount' => self::MINOR_UNITS,
            'net_amount' => self::MINOR_UNITS,
            'tax_amount' => self::MINOR_UNITS,
            'total_amount' => self::MINOR_UNITS,
        ]],
        'invoice_taxes' => ['Taxes on an invoice, by line, copied when it was issued.', [
            'tax_amount' => self::MINOR_UNITS,
        ]],
        'invoices' => ['Invoices: tax documents issued once an order counts as a sale. Copies the seller, buyer and amounts when issued; never changed, except that buyer details are erased on request.', [
            'public_id' => self::PUBLIC_ID,
            'sequence_number' => 'Number within the series, without gaps.',
            'tax_mode' => 'One of App\Shared\Money\TaxMode.',
            'buyer_erased_at' => 'When the buyer\'s personal details were erased; amounts and numbering stay.',
            'subtotal_amount' => self::MINOR_UNITS,
            'discount_amount' => self::MINOR_UNITS,
            'shipping_amount' => self::MINOR_UNITS,
            'shipping_discount_amount' => self::MINOR_UNITS,
            'tax_amount' => self::MINOR_UNITS,
            'total_amount' => self::MINOR_UNITS,
            'base_total_amount' => 'The total in minor units of base_currency.',
        ]],
        'marketing_consents' => ['Each marketing consent a customer gave or withdrew, with the wording they saw. Only ever added to.', [
            'public_id' => self::PUBLIC_ID,
            'channel' => 'One of App\Tenant\Customers\Enums\MarketingChannel.',
            'status' => 'One of App\Tenant\Customers\Enums\MarketingConsentStatus.',
            'source' => 'One of App\Tenant\Customers\Enums\MarketingConsentSource.',
        ]],
        'media' => ['Uploaded files such as product photos (spatie/laravel-medialibrary).', [
            'model_type' => 'Morph name of the record the file belongs to.',
        ]],
        'migrations' => ['Migrations that have run on this store\'s database (Laravel).', []],
        'model_has_permissions' => ['Permissions given directly to a staff member, besides their roles (spatie/laravel-permission, staff guard).', [
            'model_type' => 'Morph name of the account.',
        ]],
        'model_has_roles' => ['Roles held by staff (spatie/laravel-permission, staff guard).', [
            'model_type' => 'Morph name of the account.',
        ]],
        'order_access_tokens' => ['Temporary access for a guest to their order, without an account.', []],
        'order_addresses' => ['An order\'s billing and shipping addresses. Version 1 is what the buyer gave; a correction adds a version.', [
            'kind' => 'One of App\Tenant\Orders\Enums\OrderAddressKind.',
            'country_code' => 'ISO 3166-1 alpha-2.',
            'phone' => 'E.164.',
        ]],
        'order_contact_corrections' => ['Corrections to an order\'s contact details, each kept with who made it.', []],
        'order_discounts' => ['Discounts applied to an order, as placed.', [
            'class' => 'One of App\Tenant\Promotions\Enums\DiscountClass.',
            'discount_amount' => self::MINOR_UNITS,
        ]],
        'order_events' => ['An order\'s timeline. Only ever added to.', [
            'type' => 'One of App\Tenant\Orders\Enums\OrderEventType.',
            'actor_type' => 'Morph name of who did it; null for the system.',
        ]],
        'order_lines' => ['What was bought, copied at placement. Never changed.', [
            'public_id' => self::PUBLIC_ID,
            'tax_outcome' => 'One of App\Tenant\Pricing\Enums\TaxOutcome.',
            'unit_price_amount' => self::MINOR_UNITS,
            'subtotal_amount' => self::MINOR_UNITS,
            'discount_amount' => self::MINOR_UNITS,
            'net_amount' => self::MINOR_UNITS,
            'tax_amount' => self::MINOR_UNITS,
            'total_amount' => self::MINOR_UNITS,
        ]],
        'order_notes' => ['Staff-only notes on an order; never shown to the buyer.', [
            'public_id' => self::PUBLIC_ID,
        ]],
        'order_shipping' => ['The shipping or pickup method chosen for an order, copied at placement.', [
            'fulfilment' => 'One of App\Tenant\Shipping\Enums\ShippingFulfilment.',
            'discount_amount' => self::MINOR_UNITS,
            'net_amount' => self::MINOR_UNITS,
            'tax_amount' => self::MINOR_UNITS,
            'tax_outcome' => 'One of App\Tenant\Pricing\Enums\TaxOutcome.',
            'min_days' => 'Shortest delivery estimate, in working days.',
            'max_days' => 'Longest delivery estimate, in working days.',
        ]],
        'order_tags' => ['Staff tags on orders.', []],
        'order_taxes' => ['Taxes on an order, by line and for shipping, copied at placement.', [
            'tax_amount' => self::MINOR_UNITS,
        ]],
        'orders' => ['Orders: the permanent record of what was bought and charged. Amounts and copies are never changed; personal details are erased on request.', [
            'public_id' => self::PUBLIC_ID,
            'number' => 'Sequential number for people to read; not a secret.',
            'status' => 'One of App\Tenant\Orders\Enums\OrderStatus.',
            'payment_status' => 'One of App\Tenant\Orders\Enums\OrderPaymentStatus.',
            'fulfilment_status' => 'One of App\Tenant\Orders\Enums\FulfilmentStatus.',
            'rate_source' => 'One of App\Tenant\Pricing\Enums\ExchangeRateSource.',
            'tax_mode' => 'One of App\Shared\Money\TaxMode.',
            'tax_calculated_by' => '"rate_table", or a country tax plug-in\'s feature key.',
            'subtotal_amount' => self::MINOR_UNITS,
            'discount_amount' => self::MINOR_UNITS,
            'shipping_discount_amount' => self::MINOR_UNITS,
            'tax_amount' => self::MINOR_UNITS,
            'total_amount' => self::MINOR_UNITS,
            'cancellation_reason' => 'One of App\Tenant\Orders\Enums\CancellationReason.',
            'cancelled_by_type' => 'Morph name of who cancelled it; null when the payment-deadline sweep did.',
            'anonymised_at' => 'When the buyer\'s personal details were erased; amounts stay.',
        ]],
        'ownership_transfers' => ['Handing the store\'s ownership to another staff member. Nothing changes until they accept; one pending per store.', [
            'public_id' => self::PUBLIC_ID,
            'status' => 'One of App\Tenant\Identity\Enums\OwnershipTransferStatus.',
            'accepted_terms_of_service_id' => 'Public ID of the terms of service the new owner accepted.',
        ]],
        'ownership_transfer_kept_roles' => ['Roles the current owner keeps after a transfer.', []],
        'payment_events' => ['A payment\'s timeline. Only ever added to.', [
            'type' => 'One of App\Tenant\Payments\Enums\PaymentEventType.',
            'actor_type' => 'Morph name of who did it; null for the system.',
        ]],
        'payment_gateway_connections' => ['The store\'s connections to payment gateways. Turning one off never blocks money already in motion.', [
            'public_id' => self::PUBLIC_ID,
            'status' => 'One of App\Tenant\Payments\Enums\ConnectionStatus.',
            'mode' => 'One of App\Tenant\Payments\Enums\GatewayMode.',
        ]],
        'payment_method_settings' => ['Settings of the manual payment methods (bank transfer, cash on delivery).', [
            'method' => 'A manual method from App\Tenant\Payments\Enums\PaymentMethod.',
            'deadline_hours' => 'Hours an order may wait for payment before it is cancelled.',
        ]],
        'payment_refunds' => ['Each payment\'s share of a refund, sent back through its gateway or recorded as returned by hand.', [
            'public_id' => self::PUBLIC_ID,
            'status' => 'One of App\Tenant\Payments\Enums\RefundStatus.',
            'review_reason' => 'One of App\Tenant\Payments\Enums\RefundReviewReason, while in staff review.',
            'checks' => 'How often the gateway was asked about it.',
        ]],
        'payment_webhook_events' => ['Signed gateway webhooks, recorded once each. A webhook only prompts the store to ask the gateway. The payload is emptied after 30 days, the row removed after 180.', [
            'kind' => 'One of App\Tenant\Payments\Enums\GatewayWebhookKind.',
        ]],
        'payments' => ['Payment attempts and receipts toward orders. Only succeeded money in the order\'s currency counts.', [
            'public_id' => self::PUBLIC_ID,
            'method' => 'One of App\Tenant\Payments\Enums\PaymentMethod.',
            'is_test' => 'Made in a gateway\'s test mode.',
            'status' => 'One of App\Tenant\Payments\Enums\PaymentStatus.',
            'review_reason' => 'One of App\Tenant\Payments\Enums\ReviewReason, while in staff review.',
            'card_last4' => 'At most the last four digits, when the gateway gives them; full card details are never stored.',
            'confirmed_by_type' => 'Morph name of the staff member who confirmed a manual receipt.',
            'checks' => 'How often the gateway was asked about it.',
        ]],
        'permissions' => ['Staff permissions, synced from the permission enums in code by permissions:sync.', [
            'name' => 'Permission key, such as "orders.view".',
            'guard_name' => 'Always "staff" in this database.',
        ]],
        'personal_access_tokens' => ['Sign-in tokens for staff, customers and drivers (Laravel Sanctum).', [
            'tokenable_type' => 'Morph name of the account the token belongs to; a token works only for its own account type.',
            'token' => 'SHA-256 hash of the token; the token itself is never stored.',
        ]],
        'product_variants' => ['What is actually bought: a product\'s variant, such as Blue / M. Soft-deleted, so old references stay intact.', [
            'public_id' => self::PUBLIC_ID,
            'sku' => 'Unique in the store among variants not in the trash.',
            'price_amount' => 'In minor units of currency, the store\'s base currency. Null while not yet priced: not buyable.',
            'compare_at_price_amount' => 'Former price shown beside it, in minor units; higher than price_amount.',
            'weight_grams' => 'Required when requires_shipping.',
            'attribute_signature' => 'Normalised option values, so a product can\'t have two variants with the same combination.',
            'trashed_with_product' => 'Trashed because its product was, so restoring the product restores it.',
        ]],
        'products' => ['Products as shoppers see them; each has at least one variant. Soft-deleted, so old references stay intact.', [
            'public_id' => self::PUBLIC_ID,
            'status' => 'One of App\Tenant\Catalog\Enums\ProductStatus.',
            'name' => 'Translated name, by language code.',
            'slug' => 'Readable URL identifier, unique among products not in the trash; not changed by later edits.',
            'description' => 'Translated description, by language code.',
            'search_text' => 'Normalised names, variant identifiers and brand for search.',
        ]],
        'promotion_categories' => ['Categories a promotion includes, excludes or gives.', [
            'list' => 'One of App\Tenant\Promotions\Enums\PromotionTargetList.',
        ]],
        'promotion_codes' => ['Codes for code promotions. Unique ignoring case.', [
            'public_id' => self::PUBLIC_ID,
        ]],
        'promotion_currency_amounts' => ['A promotion\'s exact amounts in one of the store\'s other currencies, instead of converting.', [
            'discount_amount' => 'In minor units of currency_code.',
            'max_discount_amount' => 'In minor units of currency_code.',
            'min_subtotal_amount' => 'In minor units of currency_code.',
        ]],
        'promotion_customer_groups' => ['Customer groups a promotion is limited to.', []],
        'promotion_products' => ['Products a promotion includes, excludes or gives.', [
            'list' => 'One of App\Tenant\Promotions\Enums\PromotionTargetList.',
        ]],
        'promotion_redemptions' => ['Each use of a promotion: reserved at placement, committed when paid, released if not. Usage limits are counted from here.', [
            'public_id' => self::PUBLIC_ID,
            'holder_type' => 'Morph name of the checkout or order holding the use.',
            'holder_id' => 'Public ID of the checkout or order holding the use.',
            'status' => 'One of App\Tenant\Promotions\Enums\RedemptionStatus.',
            'discount_amount' => self::MINOR_UNITS,
        ]],
        'promotion_variants' => ['Variants a promotion includes, excludes or gives.', [
            'list' => 'One of App\Tenant\Promotions\Enums\PromotionTargetList.',
        ]],
        'promotions' => ['The store\'s discounts, automatic or by code. Soft-deleted when archived, so old orders keep them.', [
            'public_id' => self::PUBLIC_ID,
            'title' => 'Translated title shown to shoppers, by language code.',
            'method' => 'One of App\Tenant\Promotions\Enums\PromotionMethod.',
            'type' => 'One of App\Tenant\Promotions\Enums\PromotionType.',
            'applies_to' => 'One of App\Tenant\Promotions\Enums\PromotionAppliesTo.',
            'percent' => 'A percentage: 10 means 10%.',
            'currency' => 'ISO 4217 base currency the amounts are in; null when there are none.',
            'discount_amount' => self::BASE_MINOR_UNITS,
            'max_discount_amount' => self::BASE_MINOR_UNITS,
            'min_subtotal_amount' => self::BASE_MINOR_UNITS,
            'starts_at' => 'Inclusive.',
            'ends_at' => 'Exclusive.',
        ]],
        'refund_lines' => ['Order lines and quantities a refund covers.', [
            'tax_amount' => self::MINOR_UNITS,
        ]],
        'refunds' => ['Refunds staff asked for. The request and its amounts never change.', [
            'public_id' => self::PUBLIC_ID,
            'shipping_tax_amount' => self::MINOR_UNITS,
            'requested_by_type' => 'Morph name of the staff member who asked.',
        ]],
        'role_has_permissions' => ['Permissions in each staff role (spatie/laravel-permission).', []],
        'roles' => ['Staff roles (spatie/laravel-permission, staff guard). Names are unique ignoring case; Owner is built in.', [
            'public_id' => self::PUBLIC_ID,
            'guard_name' => 'Always "staff" in this database.',
        ]],
        'shipment_lines' => ['Order line quantities in each shipment.', []],
        'shipments' => ['Parcels or pickups of an order\'s items.', [
            'public_id' => self::PUBLIC_ID,
            'fulfilment' => 'One of App\Tenant\Shipping\Enums\ShippingFulfilment.',
            'status' => 'One of App\Tenant\Orders\Enums\ShipmentStatus.',
            'created_by_type' => 'Morph name of who created it.',
        ]],
        'shipping_method_amounts' => ['A shipping method\'s exact amounts in one of the store\'s other currencies, instead of converting.', [
            'free_over_amount' => 'In minor units of currency_code.',
            'backup_amount' => 'In minor units of currency_code.',
        ]],
        'shipping_methods' => ['Shipping, own-delivery and pickup options shoppers can choose. Archived, not deleted, so old orders keep them.', [
            'public_id' => self::PUBLIC_ID,
            'name' => 'Translated name, by language code.',
            'description' => 'Translated description, by language code.',
            'fulfilment' => 'One of App\Tenant\Shipping\Enums\ShippingFulfilment.',
            'pricing' => 'One of App\Tenant\Shipping\Enums\ShippingPricing.',
            'free_over_amount' => 'Free when shippable items after discounts reach this, in minor units of currency.',
            'pickup_instructions' => 'Translated instructions, by language code.',
            'backup_amount' => 'Price used when a carrier can\'t quote, in minor units of currency.',
            'min_days' => 'Shortest delivery estimate, in working days.',
            'max_days' => 'Longest delivery estimate, in working days.',
        ]],
        'shipping_rate_amounts' => ['A rate bracket\'s exact price in one of the store\'s other currencies, instead of converting.', [
            'price_amount' => 'In minor units of currency_code.',
        ]],
        'shipping_rates' => ['Price brackets of weight and order-value shipping methods. Brackets of a method don\'t overlap.', [
            'range_from' => 'Start of the bracket, inclusive: grams for weight pricing, minor units of the base currency for order value.',
            'range_to' => 'End of the bracket, exclusive; null for "and above".',
            'amount' => self::BASE_MINOR_UNITS,
        ]],
        'shipping_zone_regions' => ['Countries, and optionally states, a shipping zone covers. A region belongs to at most one zone.', [
            'country_code' => 'ISO 3166-1 alpha-2.',
        ]],
        'shipping_zones' => ['Groups of destinations shipping methods are offered to.', [
            'public_id' => self::PUBLIC_ID,
            'covers_rest_of_world' => 'Covers every destination no other zone does; such a zone lists no regions.',
        ]],
        'social_sign_in_attempts' => ['One-time state of a customer\'s social sign-in or linking, while they are at the provider. Short-lived.', [
            'state_hash' => 'SHA-256 of the state sent to the provider; the state itself is never stored.',
            'provider' => 'One of App\Tenant\Customers\Enums\SocialProvider.',
        ]],
        'social_sign_in_tickets' => ['Short-lived tickets for a social sign-in that needs one more step from the customer.', [
            'token_hash' => 'SHA-256 of the ticket\'s token; the token itself is never stored.',
            'provider' => 'One of App\Tenant\Customers\Enums\SocialProvider.',
        ]],
        'staff_invitation_roles' => ['Roles an invited staff member gets when they accept.', []],
        'staff_invitations' => ['Invitations to join the store\'s team: a one-time link that expires after 7 days.', [
            'public_id' => self::PUBLIC_ID,
            'token_hash' => 'SHA-256 of the link\'s token; the token itself is never stored.',
            'revoked_at' => 'When it was cancelled or replaced by a resend.',
        ]],
        'staff_member_password_reset_tokens' => ['Pending password resets for staff (Laravel password broker).', [
            'token' => 'Hash of the reset token; the token itself is never stored.',
        ]],
        'staff_members' => ['The store\'s team: the owner and the staff they invited.', [
            'public_id' => self::PUBLIC_ID,
            'email' => 'Lower case; the sign-in name.',
            'password' => 'Password hash.',
            'is_active' => 'False blocks sign-in and rejects existing tokens.',
            'two_factor_secret' => 'Authenticator secret, encrypted with the application key.',
            'two_factor_recovery_codes' => 'Hashes of the unused recovery codes; a used code is removed.',
            'two_factor_last_used_timestep' => 'Time step of the last code accepted, so no code works twice.',
        ]],
        'stock_items' => ['Stock of one variant at one location. Written only by StockLedger, with a movement for every change.', [
            'on_hand' => 'Units physically there; never below zero.',
            'reserved' => 'Units held for checkouts and orders; never below zero, but may exceed on_hand.',
        ]],
        'stock_locations' => ['Places that hold stock. Every store has one default location, which must stay active.', [
            'public_id' => self::PUBLIC_ID,
        ]],
        'stock_movements' => ['History of every stock change. Only ever added to: a trigger refuses edits and deletions.', [
            'public_id' => self::PUBLIC_ID,
            'type' => 'One of App\Tenant\Inventory\Enums\StockMovementType.',
            'reason' => 'For an adjustment, one of App\Tenant\Inventory\Enums\AdjustmentReason.',
            'causer_type' => 'Morph name of who caused it; null for the system. Set together with causer_id.',
            'source_type' => 'Morph name of the record it came from, such as a reservation. Set together with source_id.',
            'unit_cost' => 'Cost of one unit on a receipt, in the base currency as a decimal (not minor units).',
        ]],
        'stock_reservations' => ['Stock held for a checkout or an order. Only an active one holds stock.', [
            'public_id' => self::PUBLIC_ID,
            'holder_type' => 'Morph name of the checkout or order holding it.',
            'holder_id' => 'Public ID of the checkout or order holding it.',
            'quantity' => 'Units held, including any backordered part.',
            'backordered_quantity' => 'Part of quantity not covered by stock on hand when it was held.',
            'status' => 'One of App\Tenant\Inventory\Enums\ReservationStatus.',
            'expires_at' => 'When a checkout\'s hold lapses; null for an order\'s.',
        ]],
        'store_currencies' => ['Currencies the store accepts besides its base currency.', [
            'currency_code' => 'ISO 4217.',
            'margin_percent' => 'Added to the market rate: 2.5 means 2.5%.',
        ]],
        'store_settings' => ['The store\'s core settings: exactly one row (a check constraint enforces it). Modules and integrations keep their own settings.', [
            'country_code' => 'ISO 3166-1 alpha-2. Locked once prices exist.',
            'currency_code' => 'ISO 4217 base currency. Locked once prices exist.',
            'enabled_locales' => 'Language codes the store\'s content is in; includes default_locale.',
            'tax_mode' => 'One of App\Shared\Money\TaxMode. Locked once prices exist.',
            'weight_unit' => 'One of App\Tenant\Settings\Enums\WeightUnit. For display only; weights are stored in grams.',
            'dimension_unit' => 'One of App\Tenant\Settings\Enums\DimensionUnit. For display only; dimensions are stored in millimetres.',
            'require_staff_two_factor' => 'Staff must use two-factor authentication.',
            'low_stock_threshold' => 'Store-wide low-stock warning level, for variants without their own.',
            'working_days' => 'Days of the week the store works, for delivery estimates.',
        ]],
        'tax_classes' => ['Groups of variants taxed the same way. One is the default.', [
            'public_id' => self::PUBLIC_ID,
            'is_exempt' => 'Outside tax: no rate is looked up.',
            'used_for_shipping' => 'The class shipping is taxed in.',
        ]],
        'tax_rates' => ['Tax rates of a class for a country or state. A state\'s rate replaces the country\'s; rates are not added together.', [
            'public_id' => self::PUBLIC_ID,
            'country_code' => 'ISO 3166-1 alpha-2.',
        ]],
        'variant_tax_classes' => ['The tax class of each variant; a variant without one uses the default class.', []],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => [$tableComment, $columns]) {
            Schema::table($table, static fn (Blueprint $blueprint) => $blueprint->comment($tableComment));

            foreach ($columns as $column => $comment) {
                $this->commentOnColumn($table, $column, $comment);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => [, $columns]) {
            // PostgreSQL removes a comment set to an empty string.
            Schema::table($table, static fn (Blueprint $blueprint) => $blueprint->comment(''));

            foreach (array_keys($columns) as $column) {
                $this->commentOnColumn($table, $column, null);
            }
        }
    }

    private function commentOnColumn(string $table, string $column, ?string $comment): void
    {
        $connection = DB::connection($this->getConnection());
        $grammar = $connection->getSchemaGrammar();

        $connection->statement(sprintf(
            'comment on column %s.%s is %s',
            $grammar->wrapTable($table),
            $grammar->wrap($column),
            $comment === null ? 'null' : $connection->getPdo()->quote($comment),
        ));
    }
};
