<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One tax credited on one credit note line, at one of the invoice's rates. Never changed or deleted.
 *
 * @property int $id
 * @property int $credit_note_id
 * @property int $credit_note_line_id
 * @property string $name
 * @property string $country_code
 * @property string|null $state_code
 * @property Decimal $rate_percent
 * @property string $currency
 * @property Money $tax
 * @property CarbonImmutable $created_at
 */
final class CreditNoteTax extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_note_id' => 'integer',
            'credit_note_line_id' => 'integer',
            'rate_percent' => DecimalCast::class.':4',
            'tax' => MoneyCast::class.':currency',
            'created_at' => 'immutable_datetime',
        ];
    }
}
