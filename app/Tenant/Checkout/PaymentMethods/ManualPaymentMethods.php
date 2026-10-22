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
use App\Tenant\Payments\Actions\FindManualPaymentMethods;
use App\Tenant\Payments\Enums\PaymentMethod;

/**
 * Core's methods paid by hand at checkout, as the store has turned them on: a bank transfer, and cash on delivery ("pay at pickup" for a pickup) when something is sent or collected. Each gives the order its own payment deadline. They hold nothing of their own: staff confirm the money when it arrives.
 */
final readonly class ManualPaymentMethods implements CheckoutPaymentMethod
{
    public function __construct(private FindManualPaymentMethods $findManualPaymentMethods) {}

    public function offers(CheckoutContext $context): array
    {
        $offers = [];

        foreach ($this->findManualPaymentMethods->all() as $method) {
            if (! $method->enabled || ($method->isPaidOnDelivery() && $context->checkout->shipping_method_id === null)) {
                continue;
            }

            $offers[] = new OfferedPaymentMethod(
                $method->method->value,
                PaymentMethodKind::Manual,
                payOnDelivery: $method->isPaidOnDelivery(),
                paymentDueAt: $method->paymentDueAt(),
                instructions: $method->instructions,
            );
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
     * The method paid by hand a key names; null for any other key.
     */
    public static function methodOf(string $key): ?PaymentMethod
    {
        $method = PaymentMethod::tryFrom($key);

        return $method?->isManual() === true ? $method : null;
    }
}
