<?php

declare(strict_types=1);

namespace App\Tenant\Cart;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Cart\Models\Cart;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes guest carts nobody has changed for the retention period (30 days), in every store; their tokens stop working with them.
 *
 * @extends TimestampRetentionPolicy<Cart>
 */
final class GuestCartRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'guest_carts';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<Cart>
     */
    protected function query(): Builder
    {
        return Cart::query()->whereNull('customer_id');
    }

    protected function timestampColumn(): string
    {
        return 'last_activity_at';
    }
}
