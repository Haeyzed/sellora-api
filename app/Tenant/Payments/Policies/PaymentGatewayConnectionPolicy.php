<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Payments\Enums\PaymentPermission;

/**
 * Who among staff may see how the store's payment gateways are connected, and connect or change them.
 */
final class PaymentGatewayConnectionPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(PaymentPermission::PaymentsView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(PaymentPermission::PaymentsManageGateways->value);
    }
}
