<?php

declare(strict_types=1);

namespace Tests\Fixtures\Payments;

use App\Shared\Features\IntegrationServiceProvider;
use App\Tenant\Payments\PaymentGatewayRegistry;

/**
 * The second fake gateway as its own plan-gated integration, registered only by tests.
 */
final class LocalFakePaymentGatewayServiceProvider extends IntegrationServiceProvider
{
    protected function key(): string
    {
        return LocalFakePaymentGateway::KEY;
    }

    protected function registerServices(): void
    {
        $this->callAfterResolving(PaymentGatewayRegistry::class, static function (PaymentGatewayRegistry $registry): void {
            $registry->register(LocalFakePaymentGateway::KEY, LocalFakePaymentGateway::class);
        });
    }
}
