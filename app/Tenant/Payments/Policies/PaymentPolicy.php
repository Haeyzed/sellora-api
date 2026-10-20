<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Enums\PaymentPermission;

/**
 * Who among staff may see the store's payments and refunds, record money paid by hand, and give money back.
 */
final class PaymentPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(PaymentPermission::PaymentsView->value);
    }

    /**
     * Record a bank transfer or cash collected, and ask a gateway again about a payment.
     */
    public function confirm(StaffMember $actor): bool
    {
        return $actor->can(PaymentPermission::PaymentsConfirm->value);
    }

    public function refund(StaffMember $actor): bool
    {
        return $actor->can(PaymentPermission::PaymentsRefund->value);
    }
}
