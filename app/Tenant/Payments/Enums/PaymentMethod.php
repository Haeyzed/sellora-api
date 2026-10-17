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
}
