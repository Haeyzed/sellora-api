<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\PaymentMethods;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\HeldPayment;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Checkout\Events\ExchangeCreditCommitted;
use App\Tenant\Checkout\Events\ExchangeCreditReleased;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;
use LogicException;

/**
 * What the returned items of an exchange pay toward its replacement order (Step 18c): core's own payment method, never offered at checkout.
 *
 * Only PlaceReplacementOrder holds it, as the order is placed. From then on
 * it follows every hold's lifecycle: committed once the replacement is paid,
 * released if it expires or is cancelled unpaid, each announced for Returns
 * to settle the return. It can't give back what it committed: a paid
 * replacement cancelled later is left owing the refund for staff, never
 * given back silently.
 */
final readonly class ExchangeCreditPaymentMethod implements CheckoutPaymentMethod
{
    public const string KEY = 'exchange_credit';

    /**
     * Never offered: a shopper can't choose it at checkout.
     *
     * @return list<OfferedPaymentMethod>
     */
    public function offers(CheckoutContext $context): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return [self::KEY];
    }

    public function handles(string $key): bool
    {
        return $key === self::KEY;
    }

    /**
     * @throws LogicException Always: it is never offered, so checkout never asks it to hold anything.
     */
    public function hold(OfferedPaymentMethod $method, Order $order, Money $upTo): HeldPayment
    {
        throw new LogicException('The exchange credit is held only by PlaceReplacementOrder, never at checkout.');
    }

    public function commit(CheckoutPaymentHold $hold): void
    {
        ExchangeCreditCommitted::dispatch($hold);
    }

    public function release(CheckoutPaymentHold $hold): void
    {
        ExchangeCreditReleased::dispatch($hold);
    }
}
