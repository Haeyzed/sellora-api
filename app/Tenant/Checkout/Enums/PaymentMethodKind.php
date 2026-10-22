<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Enums;

/**
 * Who offers a payment method at checkout, and how it pays.
 */
enum PaymentMethodKind: string
{
    /** Core: a gateway's hosted page, after the order is placed. */
    case Gateway = 'gateway';

    /** Core: paid by hand (bank transfer, cash on delivery), confirmed by staff. */
    case Manual = 'manual';

    /** A module's (such as a gift card), applied first, covering up to an amount. */
    case Module = 'module';

    /**
     * Whether it pays what is left of the order, rather than covering up to an amount of its own.
     */
    public function paysWhatIsLeft(): bool
    {
        return $this !== self::Module;
    }
}
