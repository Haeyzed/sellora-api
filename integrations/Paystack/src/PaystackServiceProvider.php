<?php

declare(strict_types=1);

namespace Integrations\Paystack;

use App\Shared\Features\IntegrationServiceProvider;
use App\Tenant\Payments\PaymentGatewayRegistry;

/**
 * Paystack as a payment gateway integration (Step 16b): plan-gated by its key, "paystack", for starting new payments; money already in motion with it is never gated.
 */
final class PaystackServiceProvider extends IntegrationServiceProvider
{
    protected function key(): string
    {
        return PaystackGateway::KEY;
    }

    protected function registerServices(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/paystack.php', 'paystack');
        $this->callAfterResolving(PaymentGatewayRegistry::class, static function (PaymentGatewayRegistry $registry): void {
            $registry->register(PaystackGateway::KEY, PaystackGateway::class);
        });
    }
}
