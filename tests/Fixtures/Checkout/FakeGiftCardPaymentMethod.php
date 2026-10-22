<?php

declare(strict_types=1);

namespace Tests\Fixtures\Checkout;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\HeldPayment;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Checkout\Enums\PaymentMethodKind;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;

/**
 * A module's payment method for the tests, as a gift card would be: it covers up to its balance, and records each step it was asked to take.
 */
final class FakeGiftCardPaymentMethod implements CheckoutPaymentMethod
{
    public const string KEY = 'gift_card';

    /** The balance it covers up to, in minor units of the checkout's currency. */
    public static int $balance = 2000;

    /** @var list<string> Each step, as "hold:{amount}", "commit:{reference}" or "release:{reference}". */
    public static array $steps = [];

    /** How much less than asked it manages to hold, as when the card was spent elsewhere meanwhile. */
    public static int $holdsShortBy = 0;

    public static function reset(int $balance = 2000): void
    {
        self::$balance = $balance;
        self::$steps = [];
        self::$holdsShortBy = 0;
    }

    public function offers(CheckoutContext $context): array
    {
        return [new OfferedPaymentMethod(self::KEY, PaymentMethodKind::Module, covers: Money::ofMinor(self::$balance, $context->currency()))];
    }

    public function handles(string $key): bool
    {
        return $key === self::KEY;
    }

    public function hold(OfferedPaymentMethod $method, Order $order, Money $upTo): HeldPayment
    {
        $amount = Money::ofMinor(min(self::$balance, $upTo->minorAmount()) - self::$holdsShortBy, $upTo->currency());
        self::$steps[] = 'hold:'.$amount->minorAmount();

        return new HeldPayment($amount, 'gc_'.$order->public_id);
    }

    public function commit(CheckoutPaymentHold $hold): void
    {
        self::$steps[] = 'commit:'.$hold->reference;
    }

    public function release(CheckoutPaymentHold $hold): void
    {
        self::$steps[] = 'release:'.$hold->reference;
    }
}
