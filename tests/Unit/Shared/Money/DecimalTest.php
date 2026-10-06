<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;

it('rejects text that is not a decimal number', function (string $value): void {
    expect(fn (): Decimal => Decimal::of($value))->toThrow(InvalidArgumentException::class);
})->with(['abc', '1,5', '', '1.2.3']);

it('keeps exact results when adding, subtracting and multiplying', function (): void {
    $unitCost = Decimal::of('0.1');

    $total = $unitCost->add(Decimal::of('0.2'))->multiplyBy(3)->subtract(Decimal::of('0.000001'));

    expect($total->toString())->toBe('0.899999');
});

it('rounds half-up to a fixed number of decimal places', function (string $value, int $decimalPlaces, string $expected): void {
    expect(Decimal::of($value)->roundTo($decimalPlaces)->toString())->toBe($expected);
})->with([
    'exactly half rounds up' => ['2.345', 2, '2.35'],
    'a negative half rounds away from zero' => ['-2.345', 2, '-2.35'],
    'below half rounds down' => ['2.3449', 2, '2.34'],
    'adds trailing zeros when widening' => ['1.5', 4, '1.5000'],
]);

it('divides, rounding half-up to the requested decimal places', function (): void {
    $bulkCost = Decimal::of('2');

    expect($bulkCost->divideBy(3, 6)->toString())->toBe('0.666667');
});

it('refuses to divide by zero', function (): void {
    expect(fn (): Decimal => Decimal::of('1')->divideBy(0, 2))->toThrow(InvalidArgumentException::class);
});

it('refuses a negative number of decimal places', function (): void {
    expect(fn (): Decimal => Decimal::of('1.25')->roundTo(-1))->toThrow(InvalidArgumentException::class);
});

it('compares values regardless of trailing zeros', function (): void {
    $rate = Decimal::of('1.50');

    expect($rate->isEqualTo(Decimal::of('1.5')))->toBeTrue()
        ->and($rate->isGreaterThan(1))->toBeTrue()
        ->and($rate->isLessThan(2))->toBeTrue()
        ->and(Decimal::of('0.000')->isZero())->toBeTrue()
        ->and(Decimal::of('-0.01')->isNegative())->toBeTrue();
});
