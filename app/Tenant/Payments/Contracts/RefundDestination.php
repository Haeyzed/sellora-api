<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Contracts;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\DestinationRefund;
use App\Tenant\Payments\Exceptions\RefundDestinationRefusedException;
use Throwable;

/**
 * Somewhere other than the original payment that a customer can choose to have a refund go (section 13), such as store credit (Wallet) or a voucher (GiftCards). Implemented by a module and registered with RefundDestinations from its service provider; core never imports a module.
 *
 * Core's own destination, back to the original payment, is not one of these:
 * it is the default and always offered. A module's destination is offered
 * only to signed-in customers, only while its module is Enabled, and only
 * for the part paid with real money (card, transfer, cash). Once chosen, its
 * refunds are paid out whatever state the module is in, except Unavailable:
 * money already in motion is never blocked by plan gating (section 3.3).
 */
interface RefundDestination
{
    /**
     * Its key, stored on refunds and choices: lowercase letters, digits and underscores, starting with a letter, such as "store_credit". Never "original_payment", which is core's.
     */
    public function key(): string;

    /**
     * Its name as the customer sees it, in the current language.
     */
    public function label(): string;

    /**
     * Whether this customer may choose it for this order now, by the module's own rules (for example only customers with a wallet). Core has already checked that the module is Enabled and the customer signed in.
     */
    public function isOfferedFor(Order $order, Customer $customer): bool;

    /**
     * Pays the refund out to the customer here, such as crediting their store credit, and returns the module's own reference for it.
     *
     * Called outside any transaction, so it may call out to another service.
     * It must treat a refund it already paid out (by $refund->refundId) as
     * done and return the same reference: after an unknown outcome core asks
     * again.
     *
     * @throws RefundDestinationRefusedException When it definitely won't pay it out (nothing was paid): the refund fails, and the customer can choose again.
     * @throws Throwable Anything else, such as its service timing out, is an unknown outcome: core asks again later, then sends it to staff review.
     */
    public function giveBack(DestinationRefund $refund): string;
}
