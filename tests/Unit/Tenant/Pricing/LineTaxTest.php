<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Pricing\Data\AppliedTax;
use App\Tenant\Pricing\Data\LineTax;
use App\Tenant\Pricing\Data\TaxableLine;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Pricing\Data\TaxClassData;
use App\Tenant\Pricing\Data\TaxComponent;
use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Enums\TaxLineKind;
use App\Tenant\Pricing\Enums\TaxOutcome;

/*
 * Section 3.3: tax is rounded per line, half-up, in whole minor units.
 * Exclusive: tax = net × rate / 100. Inclusive: tax = gross × rate /
 * (100 + rate) and net = gross − tax, so the shown price never changes.
 * Several taxes on one line are worked out at their combined rate and split
 * by largest remainder.
 */

function taxableLine(int $minorAmount, string $currency = 'NGN', string $key = 'line'): TaxableLine
{
    return new TaxableLine($key, TaxLineKind::Goods, Money::ofMinor($minorAmount, $currency), new TaxClassData('class', 'Standard', false));
}

function taxAt(string $rate, string $name = 'VAT'): TaxComponent
{
    return new TaxComponent($name, 'NG', null, Decimal::of($rate));
}

it('adds tax on top in a tax-exclusive store and takes it out of the price in a tax-inclusive one', function (): void {
    $exclusive = LineTax::atRates(taxableLine(1000), TaxMode::Exclusive, [taxAt('7.5')]);
    expect([$exclusive->net->minorAmount(), $exclusive->tax->minorAmount(), $exclusive->gross->minorAmount()])->toBe([1000, 75, 1075])
        ->and($exclusive->outcome)->toBe(TaxOutcome::Taxed);

    $inclusive = LineTax::atRates(taxableLine(1075), TaxMode::Inclusive, [taxAt('7.5')]);
    expect([$inclusive->net->minorAmount(), $inclusive->tax->minorAmount(), $inclusive->gross->minorAmount()])->toBe([1000, 75, 1075]);
});

it('rounds a half minor unit up, on the line rather than per unit', function (): void {
    // 10 × 5% = 0.5 → 1.
    expect(LineTax::atRates(taxableLine(10), TaxMode::Exclusive, [taxAt('5')])->tax->minorAmount())->toBe(1)
        // 3 × 100 / 200 = 1.5 → 2, leaving 1 net.
        ->and(LineTax::atRates(taxableLine(3), TaxMode::Inclusive, [taxAt('100')])->net->minorAmount())->toBe(1)
        // 0.49 rounds down: 7 × 7% = 0.49.
        ->and(LineTax::atRates(taxableLine(7), TaxMode::Exclusive, [taxAt('7')])->tax->minorAmount())->toBe(0);

    // Three units of 10 at 5%: the line's 30 × 5% = 1.5 → 2, where rounding each unit (0.5 → 1) would charge 3.
    expect(LineTax::atRates(taxableLine(30), TaxMode::Exclusive, [taxAt('5')])->tax->minorAmount())->toBe(2);
});

it('works the same in currencies with no decimals and with three', function (): void {
    // ¥1,999 at 10% exclusive: 199.9 → ¥200.
    $yen = LineTax::atRates(taxableLine(1999, 'JPY'), TaxMode::Exclusive, [taxAt('10')]);
    expect($yen->tax->minorAmount())->toBe(200)->and($yen->gross->currency())->toBe('JPY');

    // 12.345 KWD inclusive of 5%: 12345 × 5 / 105 = 587.857 → 0.588 KWD.
    $dinar = LineTax::atRates(taxableLine(12345, 'KWD'), TaxMode::Inclusive, [taxAt('5')]);
    expect([$dinar->tax->minorAmount(), $dinar->net->minorAmount()])->toBe([588, 11757])->and($dinar->tax->decimalPlaces())->toBe(3);
});

it('splits a line with several taxes by largest remainder, so the parts add up to its tax', function (): void {
    // 333 at 2.5% + 5% = 7.5% → 24.975 → 25, split 8.33 : 16.67 → 8 and 17.
    $line = LineTax::atRates(taxableLine(333), TaxMode::Exclusive, [taxAt('2.5', 'State'), taxAt('5', 'County')]);

    expect($line->tax->minorAmount())->toBe(25)
        ->and(array_map(static fn ($applied): int => $applied->amount->minorAmount(), $line->taxes))->toBe([8, 17])
        ->and(array_map(static fn ($applied): string => $applied->name, $line->taxes))->toBe(['State', 'County']);

    // Equal halves of an odd tax: 9% + 9% of 1001 inclusive.
    $split = LineTax::atRates(taxableLine(1001), TaxMode::Inclusive, [taxAt('9', 'CGST'), taxAt('9', 'SGST')]);
    expect($split->tax->minorAmount())->toBe(153)
        ->and(array_map(static fn ($applied): int => $applied->amount->minorAmount(), $split->taxes))->toBe([77, 76]);
});

it('always adds net and tax up to gross, and the parts up to the tax, whatever the amount, rate and mode', function (): void {
    foreach ([TaxMode::Exclusive, TaxMode::Inclusive] as $mode) {
        foreach (['0.0001', '3', '5.5', '7.5', '12.3456', '19', '33.3333', '100'] as $rate) {
            foreach ([1, 2, 3, 7, 99, 101, 999, 1001, 33333, 1999999, 987654321] as $amount) {
                $line = LineTax::atRates(taxableLine($amount), $mode, [taxAt($rate, 'A'), taxAt('1.25', 'B')]);

                expect($line->net->add($line->tax)->minorAmount())->toBe($line->gross->minorAmount())
                    ->and(($mode === TaxMode::Inclusive ? $line->gross : $line->net)->minorAmount())->toBe($amount)
                    ->and(array_sum(array_map(static fn ($applied): int => $applied->amount->minorAmount(), $line->taxes)))->toBe($line->tax->minorAmount());
            }
        }
    }
});

it('marks a line zero-rated when every rate is 0%, and keeps the taxes it was zero-rated under', function (): void {
    $line = LineTax::atRates(taxableLine(5000), TaxMode::Inclusive, [taxAt('0')]);

    expect($line->outcome)->toBe(TaxOutcome::ZeroRated)
        ->and($line->tax->isZero())->toBeTrue()
        ->and($line->taxes)->toHaveCount(1)
        ->and($line->taxes[0]->ratePercent->toString())->toBe('0');
});

it('refuses lines and results that don\'t add up or belong together', function (): void {
    $address = new TaxAddress('NG');

    $eight = [new AppliedTax('VAT', 'NG', null, Decimal::of(8), Money::ofMinor(8, 'NGN'))];

    // Net and tax that don't add up to gross; then taxes that don't add up to the tax.
    expect(static fn () => new LineTax('x', TaxOutcome::Taxed, Money::ofMinor(100, 'NGN'), Money::ofMinor(8, 'NGN'), Money::ofMinor(107, 'NGN'), $eight))->toThrow(InvalidArgumentException::class)
        ->and(static fn () => new LineTax('x', TaxOutcome::Taxed, Money::ofMinor(100, 'NGN'), Money::ofMinor(7, 'NGN'), Money::ofMinor(107, 'NGN'), $eight))->toThrow(InvalidArgumentException::class)
        ->and(static fn () => new TaxRequest('USD', TaxMode::Exclusive, $address, $address, [taxableLine(100)]))->toThrow(InvalidArgumentException::class)
        ->and(static fn () => new TaxRequest('NGN', TaxMode::Exclusive, $address, $address, [taxableLine(100), taxableLine(200)]))->toThrow(InvalidArgumentException::class)
        ->and(static fn () => taxableLine(-1))->toThrow(InvalidArgumentException::class)
        ->and(static fn () => taxAt('-1'))->toThrow(InvalidArgumentException::class);
});
