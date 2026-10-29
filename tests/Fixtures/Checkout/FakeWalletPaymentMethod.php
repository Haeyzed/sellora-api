<?php

declare(strict_types=1);

namespace Tests\Fixtures\Checkout;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Contracts\CheckoutPaymentRefunds;
use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\HeldPayment;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Checkout\Enums\PaymentMethodKind;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;
use Throwable;

/**
 * A second module's payment method for the tests, as store credit would be, so one order can be paid by two modules (beside FakeGiftCardPaymentMethod): it covers up to its balance, gives back what it took when asked (once per hold, however often it is asked), and records each step it was asked to take.
 */
final class FakeWalletPaymentMethod implements CheckoutPaymentMethod, CheckoutPaymentRefunds
{
    public const string KEY = 'wallet';

    /** The balance it covers up to, in minor units of the checkout's currency. */
    public static int $balance = 3000;

    /** @var list<string> Each step, as "hold:{amount}", "commit:{reference}", "release:{reference}" or "refund:{reference}:{amount}". */
    public static array $steps = [];

    /** What giving back throws, as when the wallet service can't be reached; null when it works. */
    public static ?Throwable $refundFailure = null;

    /** @var array<string, true> The holds it already gave back, by reference. */
    private static array $refunded = [];

    public static function reset(int $balance = 3000): void
    {
        self::$balance = $balance;
        self::$steps = [];
        self::$refundFailure = null;
        self::$refunded = [];
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
        $amount = Money::ofMinor(min(self::$balance, $upTo->minorAmount()), $upTo->currency());
        self::$steps[] = 'hold:'.$amount->minorAmount();

        return new HeldPayment($amount, 'wl_'.$order->public_id);
    }

    public function commit(CheckoutPaymentHold $hold): void
    {
        self::$steps[] = 'commit:'.$hold->reference;
    }

    public function release(CheckoutPaymentHold $hold): void
    {
        self::$steps[] = 'release:'.$hold->reference;
    }

    public function refund(CheckoutPaymentHold $hold): void
    {
        if (self::$refundFailure !== null) {
            throw self::$refundFailure;
        }

        // Already back in the wallet: nothing more to do.
        if (isset(self::$refunded[(string) $hold->reference])) {
            return;
        }

        self::$refunded[(string) $hold->reference] = true;
        self::$steps[] = 'refund:'.$hold->reference.':'.$hold->amount->minorAmount();
    }
}
