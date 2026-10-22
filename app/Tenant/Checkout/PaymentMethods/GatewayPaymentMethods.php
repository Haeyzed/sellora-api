<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\PaymentMethods;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\HeldPayment;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Checkout\Enums\PaymentMethodKind;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\PaymentGatewayResolver;

/**
 * Core's gateways at checkout: each connected gateway that takes the order's currency from the buyer's country, through PaymentGatewayResolver, keyed "gateway:{gateway}". Paid on the gateway's hosted page once the order is placed, so it holds nothing of its own.
 *
 * A gateway in test mode makes the order a test order, for good.
 */
final readonly class GatewayPaymentMethods implements CheckoutPaymentMethod
{
    public const string PREFIX = 'gateway:';

    public function __construct(private PaymentGatewayResolver $resolver) {}

    public function offers(CheckoutContext $context): array
    {
        $offers = [];

        foreach ([false, true] as $isTest) {
            foreach ($this->resolver->forNewPayment($context->currency(), $context->buyerCountry, $isTest) as $connection) {
                $offers[] = new OfferedPaymentMethod(self::PREFIX.$connection->gateway, PaymentMethodKind::Gateway, isTest: $isTest);
            }
        }

        return $offers;
    }

    public function hold(OfferedPaymentMethod $method, Order $order, Money $upTo): HeldPayment
    {
        return new HeldPayment(Money::zero($upTo->currency()));
    }

    public function commit(CheckoutPaymentHold $hold): void {}

    public function release(CheckoutPaymentHold $hold): void {}

    /**
     * The gateway a "gateway:{gateway}" key names; null for any other key.
     */
    public static function gatewayOf(string $key): ?string
    {
        return str_starts_with($key, self::PREFIX) ? substr($key, strlen(self::PREFIX)) : null;
    }
}
