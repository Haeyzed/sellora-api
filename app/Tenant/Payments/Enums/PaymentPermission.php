<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

use App\Shared\Auth\Contracts\GrantablePermission;

/**
 * What staff can be allowed to do with the store's payments.
 */
enum PaymentPermission: string implements GrantablePermission
{
    /** See payments, refunds and how gateways are connected. */
    case PaymentsView = 'payments.view';
    /** Confirm that a bank transfer arrived or that cash on delivery was collected, and ask a gateway again about a payment. */
    case PaymentsConfirm = 'payments.confirm';
    /** Refund payments, through the gateway or as recorded by hand. */
    case PaymentsRefund = 'payments.refund';
    /** Connect, disconnect and set up payment gateways and payment methods. */
    case PaymentsManageGateways = 'payments.manage_gateways';

    public function group(): string
    {
        return 'payments';
    }

    public function isSensitive(): bool
    {
        return in_array($this, [self::PaymentsRefund, self::PaymentsManageGateways], true);
    }
}
