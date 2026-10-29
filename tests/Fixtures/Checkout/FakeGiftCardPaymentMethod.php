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
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A module's payment method for the tests, as a gift card would be: it covers up to its balance, gives back what it took when asked (once per hold, however often it is asked), and records each step it was asked to take.
 */
final class FakeGiftCardPaymentMethod implements CheckoutPaymentMethod, CheckoutPaymentRefunds
{
    public const string KEY = 'gift_card';

    /** The balance it covers up to, in minor units of the checkout's currency. */
    public static int $balance = 2000;

    /** @var list<string> Each step, as "hold:{amount}", "commit:{reference}", "release:{reference}" or "refund:{reference}:{amount}". */
    public static array $steps = [];

    /** How much less than asked it manages to hold, as when the card was spent elsewhere meanwhile. */
    public static int $holdsShortBy = 0;

    /** What giving back throws, as when the card service can't be reached or refuses; null when it works. */
    public static ?Throwable $refundFailure = null;

    /** What committing or releasing a hold throws, as when the card service can't be reached; null when it works. */
    public static ?Throwable $settleFailure = null;

    /** @var list<int> The database transaction level each time it was asked to give back: core asks outside any. */
    public static array $refundTransactionLevels = [];

    /** @var array<string, true> The holds it already gave back, by reference. */
    private static array $refunded = [];

    public static function reset(int $balance = 2000): void
    {
        self::$balance = $balance;
        self::$steps = [];
        self::$holdsShortBy = 0;
        self::$refundFailure = null;
        self::$settleFailure = null;
        self::$refundTransactionLevels = [];
        self::$refunded = [];
    }

    public function offers(CheckoutContext $context): array
    {
        return [new OfferedPaymentMethod(self::KEY, PaymentMethodKind::Module, covers: Money::ofMinor(self::$balance, $context->currency()))];
    }

    public function keys(): array
    {
        return [self::KEY];
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
        if (self::$settleFailure !== null) {
            throw self::$settleFailure;
        }

        self::$steps[] = 'commit:'.$hold->reference;
    }

    public function release(CheckoutPaymentHold $hold): void
    {
        if (self::$settleFailure !== null) {
            throw self::$settleFailure;
        }

        self::$steps[] = 'release:'.$hold->reference;
    }

    public function refund(CheckoutPaymentHold $hold): void
    {
        self::$refundTransactionLevels[] = DB::transactionLevel();

        if (self::$refundFailure !== null) {
            throw self::$refundFailure;
        }

        // Already back on the card: nothing more to do.
        if (isset(self::$refunded[(string) $hold->reference])) {
            return;
        }

        self::$refunded[(string) $hold->reference] = true;
        self::$steps[] = 'refund:'.$hold->reference.':'.$hold->amount->minorAmount();
    }
}
