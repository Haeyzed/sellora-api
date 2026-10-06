<?php

declare(strict_types=1);

namespace App\Shared\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Stores a Decimal attribute in a fixed-scale decimal column, such as an exchange rate with 12 decimal places.
 *
 * The scale must match the column's scale in its migration:
 *
 *     'exchange_rate' => DecimalCast::class.':12',
 *
 * Saving a number with more decimal places than the column holds is refused
 * instead of silently rounded; round it explicitly with Decimal::roundTo().
 *
 * @implements CastsAttributes<Decimal, Decimal>
 */
final readonly class DecimalCast implements CastsAttributes
{
    private int $decimalPlaces;

    public function __construct(string $decimalPlaces)
    {
        if (preg_match('/^\d+$/', $decimalPlaces) !== 1) {
            throw new InvalidArgumentException('DecimalCast needs the column scale, for example DecimalCast::class.\':12\'.');
        }

        $this->decimalPlaces = (int) $decimalPlaces;
    }

    /**
     * Reads the column as an exact Decimal.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws UnexpectedValueException When the column holds something other than a decimal number.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Decimal
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new UnexpectedValueException("The \"{$key}\" column on ".$model::class.' does not hold a decimal number.');
        }

        return Decimal::of($value);
    }

    /**
     * Writes the Decimal at the column's scale.
     *
     * @param  mixed  $value  Anything can arrive here at runtime (for example through fill()), so the type is checked.
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException When the value is not a Decimal, or has more decimal places than the column holds.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Decimal) {
            throw new InvalidArgumentException("The \"{$key}\" attribute on ".$model::class.' only accepts Decimal.');
        }

        $storedValue = $value->roundTo($this->decimalPlaces);

        if (! $storedValue->isEqualTo($value)) {
            throw new InvalidArgumentException(
                "The \"{$key}\" attribute on ".$model::class." holds at most {$this->decimalPlaces} decimal places; round it first.",
            );
        }

        return $storedValue->toString();
    }
}
