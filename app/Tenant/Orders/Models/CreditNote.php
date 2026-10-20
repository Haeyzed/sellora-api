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
 * A credit note (Step 15b): reduces an invoice by what one part of a refund gave back, when that refund reduced the sale.
 *
 * Copies its invoice's seller and buyer, and its own lines (returned items,
 * shipping, or a price reduction) with their taxes. Like the invoice, the
 * database refuses any change except adding its PDF once and erasure
 * clearing the buyer. Credit notes never add up to more than their invoice.
 *
 * @property int $id
 * @property string $public_id
 * @property int $invoice_id
 * @property int $order_id
 * @property string $reference The public ID of the refund part it credits.
 * @property string $refund_reference The public ID of the refund.
 * @property string $number As printed, such as "CN-2026-000007"; "TEST-CN-1042-1" for a test order.
 * @property string|null $series
 * @property int|null $sequence_number
 * @property bool $is_test
 * @property CarbonImmutable $issued_at
 * @property string $locale
 * @property string $currency
 * @property string $base_currency
 * @property Decimal $exchange_rate
 * @property TaxMode $tax_mode
 * @property string $seller_name
 * @property string $seller_trading_name
 * @property string|null $seller_tax_registration_label
 * @property string|null $seller_tax_registration_number
 * @property array<string, string|null>|null $seller_address
 * @property string|null $seller_email
 * @property string|null $seller_phone
 * @property string|null $buyer_name Null once erased.
 * @property string|null $buyer_email
 * @property string|null $buyer_phone
 * @property array<string, string|null>|null $billing_address
 * @property array<string, string|null>|null $shipping_address
 * @property CarbonImmutable|null $buyer_erased_at
 * @property string|null $footer
 * @property Money $net
 * @property Money $tax
 * @property Money $total What went back for this part, tax included.
 * @property string|null $pdf_path
 * @property CarbonImmutable|null $pdf_rendered_at
 * @property CarbonImmutable $created_at
 * @property-read Invoice $invoice
 * @property-read Collection<int, CreditNoteLine> $lines
 * @property-read Collection<int, CreditNoteTax> $taxes
 */
final class CreditNote extends Model
{
    use HasPublicId;

    public const ?string UPDATED_AT = null;

    /** The prefix of a test order's credit note number, which takes no number from the store's series. */
    public const string TEST_PREFIX = 'TEST-CN-';

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<CreditNoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<CreditNoteTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(CreditNoteTax::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'order_id' => 'integer',
            'sequence_number' => 'integer',
            'is_test' => 'boolean',
            'issued_at' => 'immutable_datetime',
            'exchange_rate' => DecimalCast::class.':12',
            'tax_mode' => TaxMode::class,
            'seller_address' => 'array',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'buyer_erased_at' => 'immutable_datetime',
            'net' => MoneyCast::class.':currency',
            'tax' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'pdf_rendered_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
