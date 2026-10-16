<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Features;
use App\Tenant\Payments\Actions\ConnectPaymentGateway;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakeGatewayScript;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Fixtures\Payments\FakePaymentGatewayServiceProvider;
use Tests\Fixtures\Payments\LocalFakePaymentGateway;
use Tests\Fixtures\Payments\LocalFakePaymentGatewayServiceProvider;

/*
|--------------------------------------------------------------------------
| For Tests\Feature\Tenant\Payments
|--------------------------------------------------------------------------
*/

/**
 * Registers the fake payment gateway as an integration and puts it on the test store's plan, or in another state; returns its script.
 *
 * @param  string  $state  "enabled", "locked" (removed by a downgrade), "disabled" (switched off by the merchant), "suspended" (the whole store) or "unavailable".
 */
function useFakeGateway(string $state = 'enabled'): FakeGatewayScript
{
    app()->register(FakePaymentGatewayServiceProvider::class);
    app()->register(LocalFakePaymentGatewayServiceProvider::class);
    putFakeGatewayInState($state);

    return app(FakeGatewayScript::class);
}

/**
 * Moves the fake gateway on the test store's plan to another state, as a downgrade, a merchant's switch or a suspension would. The local fake gateway stays on the plan, unless the whole store is suspended.
 */
function putFakeGatewayInState(string $state): void
{
    $key = FakePaymentGateway::KEY;
    $local = LocalFakePaymentGateway::KEY;
    $snapshot = match ($state) {
        'enabled' => FakeFeatureSource::snapshot(entitled: [$key, $local]),
        'locked' => FakeFeatureSource::snapshot(entitled: [$local], removed: [$key]),
        'disabled' => FakeFeatureSource::snapshot(entitled: [$key, $local], merchantDisabled: [$key]),
        'suspended' => FakeFeatureSource::snapshot(entitled: [$key, $local], isStoreSuspended: true),
        default => FakeFeatureSource::snapshot(entitled: [$local]),
    };
    $storeId = (string) test()->store->getTenantKey();

    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, $snapshot));
    app(Features::class)->forget($storeId);
}

/**
 * The fake gateway's credentials in a mode, for an account.
 *
 * @return array{secret_key: string, public_key: string}
 */
function fakeGatewayCredentials(string $mode = 'test', string $suffix = '0123456789'): array
{
    return ['secret_key' => "sk_{$mode}_{$suffix}", 'public_key' => "pk_{$mode}_{$suffix}"];
}

/**
 * Connects the fake gateway to the test store, as its owner, in a mode.
 */
function connectFakeGateway(string $mode = 'test', string $suffix = '0123456789', string $gateway = FakePaymentGateway::KEY): PaymentGatewayConnection
{
    return test()->store->run(static fn (): PaymentGatewayConnection => app(ConnectPaymentGateway::class)->handle(test()->owner, $gateway, fakeGatewayCredentials($mode, $suffix)));
}
