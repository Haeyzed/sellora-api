<?php

declare(strict_types=1);

namespace App\Tenant\Checkout;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Checkout\Models\Checkout;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes checkouts nobody has touched for the retention period (30 days), in every store: abandoned ones, and placed ones, whose order keeps its own copy of everything. They hold contact details and addresses.
 *
 * @extends TimestampRetentionPolicy<Checkout>
 */
final class CheckoutRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'checkouts';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<Checkout>
     */
    protected function query(): Builder
    {
        return Checkout::query();
    }

    protected function timestampColumn(): string
    {
        return 'last_activity_at';
    }
}
