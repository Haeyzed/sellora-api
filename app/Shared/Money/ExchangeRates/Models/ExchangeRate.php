<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates\Models;

use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One fetched market rate: how many units of a currency one US dollar bought at a moment. Central reference data for every store (section 2.1).
 *
 * Append-only: every fetch adds rows and none is ever changed (a trigger
 * refuses updates); the retention policy deletes old ones. Only
 * App\Shared\Money\ExchangeRates uses this model (an architecture rule
 * enforces it); everything else reads rates through ExchangeRates.
 *
 * @property int $id
 * @property string $currency ISO 4217.
 * @property Decimal $units_per_usd Twelve decimal places.
 * @property string $source The provider, such as "openexchangerates".
 * @property CarbonImmutable $fetched_at When the provider says the rate was taken.
 * @property CarbonImmutable|null $created_at
 */
final class ExchangeRate extends Model
{
    use CentralConnection;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'units_per_usd' => DecimalCast::class.':12',
            'fetched_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
