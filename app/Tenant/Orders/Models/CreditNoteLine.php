<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Orders\Enums\CreditNoteLineKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of a credit note: returned units of an invoice line, its shipping, or a price reduction. Never changed or deleted.
 *
 * @property int $id
 * @property int $credit_note_id
 * @property int $position
 * @property CreditNoteLineKind $kind
 * @property int|null $invoice_line_id Null for a price reduction.
 * @property string $name
 * @property string|null $variant_name
 * @property string|null $sku
 * @property int $quantity Units returned; 0 for a share of a line split across payments.
 * @property string $currency
 * @property Money $net
 * @property Money $tax
 * @property Money $total
 * @property CarbonImmutable $created_at
 * @property-read Collection<int, CreditNoteTax> $taxes
 */
final class CreditNoteLine extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

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
            'credit_note_id' => 'integer',
            'position' => 'integer',
            'kind' => CreditNoteLineKind::class,
            'invoice_line_id' => 'integer',
            'quantity' => 'integer',
            'net' => MoneyCast::class.':currency',
            'tax' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'created_at' => 'immutable_datetime',
        ];
    }
}
