<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Contracts;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\HeldPayment;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;

/**
 * A way to pay at checkout (section 6): core's gateways and methods paid by hand, and modules' (gift cards, store credit), all through the same three steps.
 *
 * Modules register theirs with CheckoutExtensions, under their feature key;
 * core offers them only while the module is enabled for the store. What a
 * method holds is held when the order is placed, inside the placement
 * transaction; committed once the order is paid; released if it expires or
 * is cancelled. Never committed for an order that wasn't paid.
 */
interface CheckoutPaymentMethod
{
    /**
     * The methods it offers for this checkout; none when it can't take this one.
     *
     * @return list<OfferedPaymentMethod>
     */
    public function offers(CheckoutContext $context): array;

    /**
     * Holds what the method covers of the order, up to the amount given, inside the placement transaction: anything it saves rolls back with it. Never calls out to another service.
     *
     * @param  OfferedPaymentMethod  $method  One of its own offers, as the quote showed it.
     * @param  Money  $upTo  What is still to cover of the order.
     */
    public function hold(OfferedPaymentMethod $method, Order $order, Money $upTo): HeldPayment;

    /**
     * Makes the hold final, once the order is paid. Called once, after the order is saved as paid.
     */
    public function commit(CheckoutPaymentHold $hold): void;

    /**
     * Gives the hold back, once the order expires or is cancelled unpaid. Called once.
     */
    public function release(CheckoutPaymentHold $hold): void;
}
