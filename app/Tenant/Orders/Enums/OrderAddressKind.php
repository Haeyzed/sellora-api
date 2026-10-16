<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * Which of an order's addresses a copy is.
 */
enum OrderAddressKind: string
{
    case Billing = 'billing';

    case Shipping = 'shipping';
}
