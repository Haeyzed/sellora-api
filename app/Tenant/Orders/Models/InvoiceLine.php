<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Orders\Enums\InvoiceLineKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of an invoice: a thing bought, or the shipping, copied from the order as it was charged. Never changed or deleted.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $position From 0: the order's lines in order, then the shipping.
 * @property InvoiceLineKind $kind
 * @property int|null $order_line_id Null for the shipping.
 * @property string $name The product's or shipping method's name, in the order's language.
 * @property string|null $variant_name
 * @property string|null $sku
 * @property int $quantity
 * @property string $currency
 * @property Money $unit_price
 * @property Money $subtotal
 * @property Money $discount
 * @property Money $net Before tax, after the discount.
 * @property Money $tax
 * @property Money $total Tax included.
 * @property CarbonImmutable $created_at
 * @property-read Collection<int, InvoiceTax> $taxes
 */
final class InvoiceLine extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

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
            'invoice_id' => 'integer',
            'position' => 'integer',
            'kind' => InvoiceLineKind::class,
            'order_line_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => MoneyCast::class.':currency',
            'subtotal' => MoneyCast::class.':currency',
            'discount' => MoneyCast::class.':currency',
            'net' => MoneyCast::class.':currency',
            'tax' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'created_at' => 'immutable_datetime',
        ];
    }
}
