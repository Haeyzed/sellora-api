<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Contracts;

use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use Throwable;

/**
 * A module's payment method giving back what it committed for an order once the order is cancelled after it was paid (section 13), such as a gift card's balance put back on the card. Implemented beside CheckoutPaymentMethod by the module's own method; core never imports a module.
 *
 * Core asks whatever state the module is in, except Unavailable: money
 * already taken is always given back, even when the module is Locked,
 * Disabled or the store Suspended (section 3.3). Core asks once per hold,
 * and asks again only after a failure, but a module must still treat a hold
 * it already gave back as done: two retries can overlap.
 */
interface CheckoutPaymentRefunds
{
    /**
     * Gives back what the hold took, all of it ($hold->amount). Called outside any transaction, so it may call out to another service.
     *
     * @throws Throwable When it can't give it back now, for example its service can't be reached or it refuses: core records why, keeps the order owing the refund for staff, and tries again later.
     */
    public function refund(CheckoutPaymentHold $hold): void;
}
