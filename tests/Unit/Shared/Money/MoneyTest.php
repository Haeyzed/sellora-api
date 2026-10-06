<?php

declare(strict_types=1);

use App\Shared\Money\CurrencyMismatchException;
use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Shared\Money\UnknownCurrencyException;

/**
 * @param  array<array-key, Money>  $amounts
 * @return array<array-key, int>
 */
function minorAmountsOf(array $amounts): array
{
    return array_map(static fn (Money $amount): int => $amount->minorAmount(), $amounts);
}

it('uses each currency\'s own number of decimal places from ISO 4217', function (string $currency, int $expectedDecimalPlaces): void {
    $amount = Money::zero($currency);

    expect($amount->decimalPlaces())->toBe($expectedDecimalPlaces);
})->with([
    'US dollar' => ['USD', 2],
    'Nigerian naira' => ['NGN', 2],
    'Japanese yen' => ['JPY', 0],
    'Ugandan shilling' => ['UGX', 0],
    'Kuwaiti dinar' => ['KWD', 3],
    'Bahraini dinar' => ['BHD', 3],
    'Tunisian dinar' => ['TND', 3],
    'Chilean unit of account' => ['CLF', 4],
]);

it('rejects currency codes that are not ISO 4217', function (string $currency): void {
    expect(fn (): Money => Money::ofMinor(100, $currency))->toThrow(UnknownCurrencyException::class);
})->with(['XYZ', 'usd', '', ' USD']);

it('keeps amounts in whole minor units', function (): void {
    $amount = Money::ofMinor(1999, 'USD');

    expect($amount->minorAmount())->toBe(1999)
        ->and($amount->currency())->toBe('USD');
});

it('rounds internal figures half-up to the currency\'s minor unit', function (string $decimal, string $currency, int $expectedMinorAmount): void {
    $amount = Money::fromDecimal(Decimal::of($decimal), $currency);

    expect($amount->minorAmount())->toBe($expectedMinorAmount);
})->with([
    'exactly half a cent rounds up' => ['10.005', 'USD', 1001],
    'just under half a cent rounds down' => ['10.0049', 'USD', 1000],
    'a negative half rounds away from zero' => ['-10.005', 'USD', -1001],
    'half a yen rounds up to a whole yen' => ['100.5', 'JPY', 101],
    'half a fils rounds up in a 3-decimal currency' => ['1.0005', 'KWD', 1001],
]);

it('adds and subtracts amounts in the same currency', function (): void {
    $price = Money::ofMinor(1999, 'NGN');

    $total = $price->add(Money::ofMinor(501, 'NGN'))->subtract(Money::ofMinor(1000, 'NGN'));

    expect($total->minorAmount())->toBe(1500);
});

it('refuses to combine amounts in different currencies', function (Closure $combine): void {
    expect($combine)->toThrow(CurrencyMismatchException::class);
})->with([
    'adding' => [fn (): Money => Money::ofMinor(100, 'USD')->add(Money::ofMinor(100, 'EUR'))],
    'subtracting' => [fn (): Money => Money::ofMinor(100, 'USD')->subtract(Money::ofMinor(100, 'EUR'))],
    'comparing' => [fn (): bool => Money::ofMinor(100, 'USD')->isGreaterThan(Money::ofMinor(100, 'EUR'))],
    'summing' => [fn (): Money => Money::sum('USD', [Money::ofMinor(100, 'USD'), Money::ofMinor(100, 'EUR')])],
]);

it('adds up a list of amounts, giving zero for an empty list', function (): void {
    $lineTotals = [Money::ofMinor(1000, 'USD'), Money::ofMinor(2550, 'USD'), Money::ofMinor(1, 'USD')];

    expect(Money::sum('USD', $lineTotals)->minorAmount())->toBe(3551)
        ->and(Money::sum('USD', [])->isZero())->toBeTrue();
});

it('multiplies by a quantity exactly', function (): void {
    $unitPrice = Money::ofMinor(1999, 'USD');

    expect($unitPrice->multiplyBy(3)->minorAmount())->toBe(5997);
});

it('rounds a percentage half-up, not to the nearest even cent', function (): void {
    $price = Money::ofMinor(1000, 'USD');

    $tax = $price->multiplyBy(Decimal::of('0.0125'));

    expect($tax->minorAmount())->toBe(13);
});

it('splits an amount so the parts always add up exactly to the whole', function (): void {
    $discount = Money::ofMinor(100, 'USD');

    $shares = $discount->allocate(['first-line' => 1, 'second-line' => 1, 'third-line' => 1]);

    expect(minorAmountsOf($shares))->toBe(['first-line' => 34, 'second-line' => 33, 'third-line' => 33]);
});

it('gives leftover minor units to the parts that lost the most to rounding', function (): void {
    $discount = Money::ofMinor(100, 'USD');

    $shares = $discount->allocate([2, 3, 1]);

    expect(minorAmountsOf($shares))->toBe([33, 50, 17]);
});

it('splits amounts in currencies with 0 and 3 decimal places at their own minor unit', function (Money $amount, array $weights, array $expectedMinorAmounts): void {
    expect(minorAmountsOf($amount->allocate($weights)))->toBe($expectedMinorAmounts);
})->with([
    'yen' => [fn (): Money => Money::ofMinor(1000, 'JPY'), [1, 1, 1], [334, 333, 333]],
    'Kuwaiti dinar' => [fn (): Money => Money::ofMinor(1000, 'KWD'), [1, 2], [333, 667]],
]);

it('splits a negative amount, such as a refund, keeping the sign on every part', function (): void {
    $refund = Money::ofMinor(-100, 'USD');

    expect(minorAmountsOf($refund->allocate([1, 1, 1])))->toBe([-34, -33, -33]);
});

it('splits by decimal weights such as unit costs', function (): void {
    $freight = Money::ofMinor(1000, 'USD');

    $shares = $freight->allocate([Decimal::of('0.5'), Decimal::of('1.5')]);

    expect(minorAmountsOf($shares))->toBe([250, 750]);
});

it('splits zero into zero parts even when every weight is zero', function (): void {
    $noDiscount = Money::zero('USD');

    expect(minorAmountsOf($noDiscount->allocate([0, 0])))->toBe([0, 0]);
});

it('refuses to lose money by splitting a non-zero amount across weights that are all zero', function (): void {
    $discount = Money::ofMinor(100, 'USD');

    expect(fn (): array => $discount->allocate([0, 0]))->toThrow(InvalidArgumentException::class);
});

it('refuses negative weights', function (): void {
    $discount = Money::ofMinor(100, 'USD');

    expect(fn (): array => $discount->allocate([1, -1]))->toThrow(InvalidArgumentException::class);
});

it('converts to another currency with the given rate, rounding half-up', function (Money $amount, string $targetCurrency, string $rate, int $expectedMinorAmount): void {
    $converted = $amount->convertTo($targetCurrency, Decimal::of($rate));

    expect($converted->currency())->toBe($targetCurrency)
        ->and($converted->minorAmount())->toBe($expectedMinorAmount);
})->with([
    'dollars to naira' => [fn (): Money => Money::ofMinor(10000, 'USD'), 'NGN', '1550.25', 15502500],
    'a cent to half-yen rounds up' => [fn (): Money => Money::ofMinor(1, 'USD'), 'JPY', '150', 2],
    'yen to Kuwaiti dinar' => [fn (): Money => Money::ofMinor(1000, 'JPY'), 'KWD', '0.002055', 2055],
]);

it('refuses an exchange rate that is zero or negative', function (string $rate): void {
    $amount = Money::ofMinor(100, 'USD');

    expect(fn (): Money => $amount->convertTo('EUR', Decimal::of($rate)))->toThrow(InvalidArgumentException::class);
})->with(['0', '-1.2']);

it('refuses to convert a currency to itself at a rate other than 1', function (): void {
    $amount = Money::ofMinor(100, 'USD');

    expect(fn (): Money => $amount->convertTo('USD', Decimal::of('1.1')))->toThrow(InvalidArgumentException::class);
});

it('never treats amounts in different currencies as equal', function (): void {
    expect(Money::ofMinor(100, 'USD')->isEqualTo(Money::ofMinor(100, 'EUR')))->toBeFalse()
        ->and(Money::ofMinor(100, 'USD')->isEqualTo(Money::ofMinor(100, 'USD')))->toBeTrue();
});

it('reports the sign of an amount', function (): void {
    $refund = Money::ofMinor(500, 'USD')->negate();

    expect($refund->minorAmount())->toBe(-500)
        ->and($refund->isNegative())->toBeTrue()
        ->and($refund->isPositive())->toBeFalse()
        ->and($refund->isZero())->toBeFalse();
});

it('formats amounts for display in the given locale', function (Money $amount, string $locale, string $expected): void {
    expect($amount->format($locale))->toBe($expected);
})->with([
    'dollars in English' => [fn (): Money => Money::ofMinor(125000, 'USD'), 'en', '$1,250.00'],
    'yen without decimals' => [fn (): Money => Money::ofMinor(1250, 'JPY'), 'en', '¥1,250'],
    'euros in German' => [fn (): Money => Money::ofMinor(125000, 'EUR'), 'de', "1.250,00\u{a0}€"],
]);
