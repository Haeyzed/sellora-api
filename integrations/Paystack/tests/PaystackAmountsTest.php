<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use Integrations\Paystack\Exceptions\PaystackAmountException;
use Integrations\Paystack\PaystackAmounts;

/*
 * Step 16b.1: Money crosses the gateway contract; Paystack wants whole
 * numbers of its own units per currency. Only the five launch currencies,
 * whose units a real test-mode payment confirmed, convert; nothing is ever
 * rounded.
 */

it('converts each launch currency to Paystack\'s units and back exactly', function (string $currency): void {
    $amounts = new PaystackAmounts;
    $money = Money::ofMinor(1234567, $currency);

    expect($amounts->toPaystack($money))->toBe(1234567)
        ->and($amounts->fromPaystack(1234567, $currency)->isEqualTo($money))->toBeTrue()
        ->and($amounts->fromPaystack(1234567, mb_strtolower($currency))->currency())->toBe($currency)
        ->and($amounts->toPaystack(Money::ofMinor(1, $currency)))->toBe(1);
})->with(['NGN', 'GHS', 'ZAR', 'KES', 'USD']);

it('launches with only NGN, GHS, ZAR, KES and USD', function (): void {
    expect(array_keys(PaystackAmounts::CONFIRMED))->toBe(['NGN', 'GHS', 'ZAR', 'KES', 'USD']);
});

it('refuses currencies whose Paystack units no test-mode payment has confirmed, both ways', function (string $currency): void {
    $amounts = new PaystackAmounts;

    expect(static fn () => $amounts->toPaystack(Money::ofMinor(1000, $currency)))->toThrow(PaystackAmountException::class, 'not confirmed')
        ->and(static fn () => $amounts->fromPaystack(1000, $currency))->toThrow(PaystackAmountException::class, 'not confirmed');
})->with(['XOF', 'EGP', 'KWD', 'JPY', 'EUR']);

it('sends Paystack only amounts above zero, and refuses a negative amount from it', function (): void {
    $amounts = new PaystackAmounts;

    expect(static fn () => $amounts->toPaystack(Money::zero('NGN')))->toThrow(PaystackAmountException::class, 'above zero')
        ->and(static fn () => $amounts->toPaystack(Money::ofMinor(-500, 'NGN')))->toThrow(PaystackAmountException::class, 'above zero')
        ->and(static fn () => $amounts->fromPaystack(-1, 'NGN'))->toThrow(PaystackAmountException::class, 'negative')
        // Paystack reports zero for, say, no fees.
        ->and($amounts->fromPaystack(0, 'NGN')->isZero())->toBeTrue();
});

it('refuses an amount too large to convert rather than letting it overflow', function (): void {
    expect(static fn () => (new PaystackAmounts)->toPaystack(Money::ofMinor(PHP_INT_MAX, 'NGN')))->toThrow(PaystackAmountException::class, 'too large');
});

it('scales between a currency\'s decimals and Paystack\'s units when they differ, refusing anything inexact', function (): void {
    // Not launch currencies: what adding one whose Paystack units differ from its decimals would do.
    $amounts = new PaystackAmounts(['XOF' => 100, 'KWD' => 100]);

    expect($amounts->toPaystack(Money::ofMinor(1500, 'XOF')))->toBe(150000)
        ->and($amounts->fromPaystack(150000, 'XOF')->minorAmount())->toBe(1500)
        ->and(static fn () => $amounts->fromPaystack(150050, 'XOF'))->toThrow(PaystackAmountException::class, 'exactly')
        // KWD has three decimals: 1.005 is 100.5 of these units.
        ->and(static fn () => $amounts->toPaystack(Money::ofMinor(1005, 'KWD')))->toThrow(PaystackAmountException::class, 'exactly')
        ->and($amounts->toPaystack(Money::ofMinor(1010, 'KWD')))->toBe(101);
});
