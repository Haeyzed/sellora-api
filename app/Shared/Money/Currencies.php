<?php

declare(strict_types=1);

namespace App\Shared\Money;

use Brick\Money\Currency as BrickCurrency;
use Brick\Money\CurrencyType;
use Brick\Money\Exception\UnknownCurrencyException as BrickUnknownCurrencyException;
use Brick\Money\IsoCurrencyProvider;

/**
 * The ISO 4217 currencies in use today: which codes exist, their names and decimal places, and which ones each country uses.
 *
 * The single source of truth for currencies, so a store's currency and the
 * number of decimals it is shown with always agree with what Money does.
 * Reference data such as the world package's currency table is never used for
 * this: its precision can disagree with ISO 4217.
 */
final class Currencies
{
    /**
     * Whether the code is a current ISO 4217 currency, such as "NGN". Historical currencies don't count.
     */
    public static function isKnown(string $currencyCode): bool
    {
        try {
            return IsoCurrencyProvider::getInstance()->getCurrency($currencyCode)->getCurrencyType() === CurrencyType::IsoCurrent;
        } catch (BrickUnknownCurrencyException) {
            return false;
        }
    }

    /**
     * Every current ISO 4217 code, sorted.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        $codes = [];

        foreach (IsoCurrencyProvider::getInstance()->getAvailableCurrencies() as $currency) {
            if ($currency->getCurrencyType() === CurrencyType::IsoCurrent) {
                $codes[] = $currency->getCurrencyCode();
            }
        }

        sort($codes);

        return $codes;
    }

    /**
     * The currency's English name, such as "Nigerian Naira".
     *
     * @throws UnknownCurrencyException When the code isn't a current ISO 4217 currency.
     */
    public static function name(string $currencyCode): string
    {
        return self::currency($currencyCode)->getName();
    }

    /**
     * How many decimal places the currency's minor unit has: 2 for NGN and USD, 0 for JPY, 3 for KWD.
     *
     * @throws UnknownCurrencyException When the code isn't a current ISO 4217 currency.
     */
    public static function decimalPlaces(string $currencyCode): int
    {
        return self::currency($currencyCode)->getDefaultFractionDigits();
    }

    /**
     * The current currencies a country uses, by ISO 3166-1 alpha-2 code; usually one, sometimes several (Bhutan uses BTN and INR), sometimes none.
     *
     * @return list<string>
     */
    public static function forCountry(string $countryCode): array
    {
        $codes = [];

        foreach (IsoCurrencyProvider::getInstance()->getCurrenciesForCountry(mb_strtoupper($countryCode)) as $currency) {
            if ($currency->getCurrencyType() === CurrencyType::IsoCurrent) {
                $codes[] = $currency->getCurrencyCode();
            }
        }

        return $codes;
    }

    /**
     * @throws UnknownCurrencyException
     */
    private static function currency(string $currencyCode): BrickCurrency
    {
        if (! self::isKnown($currencyCode)) {
            throw UnknownCurrencyException::forCode($currencyCode);
        }

        return IsoCurrencyProvider::getInstance()->getCurrency($currencyCode);
    }
}
