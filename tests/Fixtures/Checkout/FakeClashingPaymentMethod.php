<?php

declare(strict_types=1);

namespace Tests\Fixtures\Checkout;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\HeldPayment;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;

/**
 * A module's payment method for the tests that claims whatever keys the test gives it, to register one that clashes with another method's keys.
 */
final class FakeClashingPaymentMethod implements CheckoutPaymentMethod
{
    /** @var list<string> The keys it claims. */
    public static array $keys = [];

    public function offers(CheckoutContext $context): array
    {
        return [];
    }

    public function keys(): array
    {
        return self::$keys;
    }

    public function handles(string $key): bool
    {
        return in_array($key, self::$keys, true);
    }

    public function hold(OfferedPaymentMethod $method, Order $order, Money $upTo): HeldPayment
    {
        return new HeldPayment(Money::zero($upTo->currency()));
    }

    public function commit(CheckoutPaymentHold $hold): void {}

    public function release(CheckoutPaymentHold $hold): void {}
}
