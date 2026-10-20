<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Enums\OrderPermission;

/**
 * Who among staff may see, change and fulfil the store's orders. Customers see only their own, through scoped queries, never through this.
 */
final class OrderPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(OrderPermission::OrdersView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(OrderPermission::OrdersManage->value);
    }

    public function fulfil(StaffMember $actor): bool
    {
        return $actor->can(OrderPermission::OrdersFulfil->value);
    }

    /**
     * Invoices and credit notes, apart from seeing orders: they are tax records, kept after the order's buyer is erased.
     */
    public function viewInvoices(StaffMember $actor): bool
    {
        return $actor->can(OrderPermission::InvoicesView->value);
    }
}
