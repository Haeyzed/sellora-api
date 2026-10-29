<?php

declare(strict_types=1);

use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\PaymentMethods\GatewayPaymentMethods;
use App\Tenant\Checkout\PaymentMethods\ManualPaymentMethods;
use Tests\Fixtures\Checkout\FakeClashingPaymentMethod;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Checkout\FakeWalletPaymentMethod;

/*
 * Each payment method key belongs to one payment method only, or a hold
 * saved under it could be committed, released or given back by the wrong
 * one. A module whose service provider registers a method that shares a key
 * with one already registered stops the app at boot, saying which two.
 */

beforeEach(function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
});

it('refuses at boot a module whose payment method uses a key another method already has', function (string $key, string $owner): void {
    FakeClashingPaymentMethod::$keys = ['store_credit', $key];

    expect(static fn () => app()->register(clashingModuleProvider()))
        ->toThrow(LogicException::class, "The payment methods {$owner} and ".FakeClashingPaymentMethod::class." both use the key \"{$key}\": each key must belong to one payment method only.");

    // Nothing was registered: the key still belongs to its owner.
    expect(app(CheckoutExtensions::class)->paymentMethodFor($key))->toBeInstanceOf($owner);
})->with([
    'another module\'s' => [FakeGiftCardPaymentMethod::KEY, FakeGiftCardPaymentMethod::class],
    'a method paid by hand' => ['bank_transfer', ManualPaymentMethods::class],
    'a gateway\'s' => ['gateway:paystack', GatewayPaymentMethods::class],
    // A gateway since removed still has its holds looked after by core.
    'a removed gateway\'s' => ['gateway:retired', GatewayPaymentMethods::class],
]);

it('registers modules\' payment methods whose keys are all their own', function (): void {
    FakeClashingPaymentMethod::$keys = ['store_credit'];

    app(CheckoutExtensions::class)->addPaymentMethod(FakeWalletPaymentMethod::class);
    app()->register(clashingModuleProvider());

    expect(app(CheckoutExtensions::class)->paymentMethodFor('store_credit'))->toBeInstanceOf(FakeClashingPaymentMethod::class)
        ->and(app(CheckoutExtensions::class)->paymentMethodFor(FakeWalletPaymentMethod::KEY))->toBeInstanceOf(FakeWalletPaymentMethod::class)
        ->and(app(CheckoutExtensions::class)->paymentMethodFor(FakeGiftCardPaymentMethod::KEY))->toBeInstanceOf(FakeGiftCardPaymentMethod::class);
});

it('names every gateway the platform has among core\'s keys, and every method paid by hand', function (): void {
    expect(app(GatewayPaymentMethods::class)->keys())->toContain('gateway:paystack')
        ->and(app(ManualPaymentMethods::class)->keys())->toBe(['bank_transfer', 'cash_on_delivery']);
});
