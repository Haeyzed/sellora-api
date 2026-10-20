<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * How a payment is made: through a gateway the store connected, or one of the core methods staff confirm by hand.
 */
enum PaymentMethod: string
{
    /** On a gateway's hosted page; confirmed only by asking the gateway. */
    case Gateway = 'gateway';
    /** To the store's bank account; staff confirm it arrived. */
    case BankTransfer = 'bank_transfer';
    /** In cash when the order is delivered; staff (and later drivers) record it as collected. */
    case CashOnDelivery = 'cash_on_delivery';

    /**
     * Paid by hand, and confirmed by staff recording what arrived.
     */
    public function isManual(): bool
    {
        return $this !== self::Gateway;
    }

    /**
     * The methods paid by hand, which each store sets up.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::BankTransfer, self::CashOnDelivery];
    }
}
