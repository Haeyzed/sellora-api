<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Shared\Money\TaxMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tax invoice for a sale (Step 15b), issued once the order is paid in full; at most one per order.
 *
 * Every value is a copy made when it was issued (seller, buyer, addresses,
 * lines, each tax, currency and rate), so nothing is read live to show or
 * render it, and the database refuses any change. Only its PDF is added once
 * rendered, and the buyer's details are cleared by erasure once the store's
 * tax-record retention ends. Amounts are in its currency, in minor units.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property string $number As printed, such as "INV-2026-000042"; "TEST-1042" for a test order.
 * @property string|null $series "2026", or "all" when numbered continuously; null for a test order, which takes no number.
 * @property int|null $sequence_number
 * @property bool $is_test
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $paid_at When the order was paid in full: the date of the sale.
 * @property string $locale
 * @property string $currency
 * @property string $base_currency
 * @property Decimal $exchange_rate Base amount × rate = amount in its currency.
 * @property TaxMode $tax_mode
 * @property string $tax_country_code
 * @property string|null $tax_state_code
 * @property string $seller_name The store's legal name, or its name.
 * @property string $seller_trading_name The store's name.
 * @property string|null $seller_tax_registration_label
 * @property string|null $seller_tax_registration_number
 * @property array{line1: string|null, line2: string|null, city: string|null, state_code: string|null, postal_code: string|null, country_code: string}|null $seller_address
 * @property string|null $seller_email
 * @property string|null $seller_phone
 * @property string|null $buyer_name Null once erased.
 * @property string|null $buyer_email
 * @property string|null $buyer_phone
 * @property array<string, string|null>|null $billing_address
 * @property array<string, string|null>|null $shipping_address
 * @property CarbonImmutable|null $buyer_erased_at
 * @property string|null $footer
 * @property Money $subtotal
 * @property Money $discount
 * @property Money $shipping
 * @property Money $shipping_discount
 * @property Money $tax
 * @property Money $total
 * @property Money $base_total
 * @property string|null $pdf_path Where its PDF is stored, privately; null until rendered.
 * @property CarbonImmutable|null $pdf_rendered_at
 * @property CarbonImmutable $created_at
 * @property-read Order $order
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read Collection<int, InvoiceTax> $taxes
 */
final class Invoice extends Model
{
    use HasPublicId;

    public const ?string UPDATED_AT = null;

    /** The prefix of a test order's invoice number, which takes no number from the store's series. */
    public const string TEST_PREFIX = 'TEST-';

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<InvoiceTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceTax::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'sequence_number' => 'integer',
            'is_test' => 'boolean',
            'issued_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'exchange_rate' => DecimalCast::class.':12',
            'tax_mode' => TaxMode::class,
            'seller_address' => 'array',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'buyer_erased_at' => 'immutable_datetime',
            'subtotal' => MoneyCast::class.':currency',
            'discount' => MoneyCast::class.':currency',
            'shipping' => MoneyCast::class.':currency',
            'shipping_discount' => MoneyCast::class.':currency',
            'tax' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'base_total' => MoneyCast::class.':base_currency',
            'pdf_rendered_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
