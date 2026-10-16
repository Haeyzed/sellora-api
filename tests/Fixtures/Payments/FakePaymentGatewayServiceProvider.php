<?php

declare(strict_types=1);

namespace Tests\Fixtures\Payments;

use App\Shared\Features\IntegrationServiceProvider;
use App\Tenant\Payments\PaymentGatewayRegistry;

/**
 * The fake payment gateway as an integration, plan-gated like a real one, registered only by tests.
 */
final class FakePaymentGatewayServiceProvider extends IntegrationServiceProvider
{
    protected function key(): string
    {
        return FakePaymentGateway::KEY;
    }

    protected function registerServices(): void
    {
        $this->app->singleton(FakeGatewayScript::class);
        $this->callAfterResolving(PaymentGatewayRegistry::class, static function (PaymentGatewayRegistry $registry): void {
            $registry->register(FakePaymentGateway::KEY, FakePaymentGateway::class);
        });
    }
}
