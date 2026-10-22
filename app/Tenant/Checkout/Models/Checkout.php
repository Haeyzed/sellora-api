<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Checkout\Enums\CheckoutStatus;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Shipping\Models\ShippingMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a shopper has told the store on the way from their cart to an order (Step 16), and the quote they were last shown.
 *
 * Placing the order sends back the quote's version; everything is worked out
 * again under the locks and must come to the same quote, or the shopper is
 * shown the new one. The exchange rate shown is kept with the quote until it
 * expires. Nothing is held for an open checkout: stock and promotion uses are
 * held only once the order is placed. One open checkout per cart.
 *
 * @property int $id
 * @property string $public_id
 * @property int $cart_id
 * @property int|null $customer_id
 * @property CheckoutStatus $status
 * @property string|null $contact_email In lower case.
 * @property string|null $contact_name
 * @property string|null $contact_phone E.164.
 * @property array<string, string|null>|null $shipping_address As AddressSnapshot::toArray() gives it.
 * @property array<string, string|null>|null $billing_address Null while the billing address is the shipping address.
 * @property int|null $shipping_method_id
 * @property string|null $payment_method The key of core's method chosen to pay what is left.
 * @property list<string> $module_payment_methods Keys of modules' methods applied first, in order.
 * @property string|null $buyer_tax_id
 * @property string|null $buyer_tax_id_country
 * @property bool $marketing_email_consent Only when the shopper explicitly said yes.
 * @property bool $marketing_sms_consent Only when the shopper explicitly said yes.
 * @property string|null $customer_note
 * @property string $locale
 * @property int $quote_version
 * @property string|null $quote_hash
 * @property CarbonImmutable|null $quote_expires_at
 * @property int|null $cart_version
 * @property array<string, string|null>|null $exchange_rate The rate the last quote was priced at.
 * @property int|null $order_id
 * @property CarbonImmutable|null $placed_at
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Cart $cart
 * @property-read Customer|null $customer
 * @property-read ShippingMethod|null $shippingMethod
 * @property-read Order|null $order
 */
final class Checkout extends Model
{
    use HasPublicId;

    protected $fillable = [];

    protected $hidden = [
        'id',
        'cart_id',
        'customer_id',
        'shipping_method_id',
        'order_id',
        'quote_hash',
    ];

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'customer_id' => 'integer',
            'shipping_method_id' => 'integer',
            'order_id' => 'integer',
            'status' => CheckoutStatus::class,
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'module_payment_methods' => 'array',
            'marketing_email_consent' => 'boolean',
            'marketing_sms_consent' => 'boolean',
            'quote_version' => 'integer',
            'quote_expires_at' => 'immutable_datetime',
            'cart_version' => 'integer',
            'exchange_rate' => 'array',
            'placed_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
