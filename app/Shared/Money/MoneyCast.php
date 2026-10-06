<?php

declare(strict_types=1);

namespace App\Shared\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Stores a Money attribute in two columns: the amount in minor units and its currency code.
 *
 * By default the attribute "price" uses the columns "price_amount" and
 * "price_currency". A table with several amounts in one currency (an order's
 * subtotal, tax and total) can share one currency column instead:
 *
 *     'price' => MoneyCast::class,
 *     'total' => MoneyCast::class.':currency',
 *
 * A shared currency column can't be switched to another currency through one
 * attribute, because that would silently change the currency of every other
 * amount using it.
 *
 * @implements CastsAttributes<Money, Money>
 */
final readonly class MoneyCast implements CastsAttributes
{
    public function __construct(private ?string $sharedCurrencyColumn = null) {}

    /**
     * Builds the Money value from the amount and currency columns.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws UnexpectedValueException When an amount is stored without a currency.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $minorAmount = $attributes[$this->amountColumn($key)] ?? null;

        if ($minorAmount === null) {
            return null;
        }

        if (! is_int($minorAmount) && ! (is_string($minorAmount) && preg_match('/^-?\d+$/', $minorAmount) === 1)) {
            throw new UnexpectedValueException("The \"{$key}\" amount on ".$model::class.' is not a whole number of minor units.');
        }

        $currency = $attributes[$this->currencyColumn($key)] ?? null;

        if (! is_string($currency) || $currency === '') {
            throw new UnexpectedValueException("The \"{$key}\" amount on ".$model::class.' has no currency.');
        }

        return Money::ofMinor((int) $minorAmount, $currency);
    }

    /**
     * Splits the Money value into the amount and currency columns.
     *
     * @param  mixed  $value  Anything can arrive here at runtime (for example through fill()), so the type is checked.
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     *
     * @throws InvalidArgumentException When the value is not Money or null.
     * @throws CurrencyMismatchException When a shared currency column already holds a different currency.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return $this->sharedCurrencyColumn === null
                ? [$this->amountColumn($key) => null, $this->currencyColumn($key) => null]
                : [$this->amountColumn($key) => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("The \"{$key}\" attribute on ".$model::class.' only accepts Money.');
        }

        $this->ensureSharedCurrencyMatches($value, $attributes);

        return [
            $this->amountColumn($key) => $value->minorAmount(),
            $this->currencyColumn($key) => $value->currency(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ensureSharedCurrencyMatches(Money $value, array $attributes): void
    {
        if ($this->sharedCurrencyColumn === null) {
            return;
        }

        $currentCurrency = $attributes[$this->sharedCurrencyColumn] ?? null;

        if (is_string($currentCurrency) && $currentCurrency !== '' && $currentCurrency !== $value->currency()) {
            throw CurrencyMismatchException::between($currentCurrency, $value->currency());
        }
    }

    private function amountColumn(string $key): string
    {
        return $key.'_amount';
    }

    private function currencyColumn(string $key): string
    {
        return $this->sharedCurrencyColumn ?? $key.'_currency';
    }
}
