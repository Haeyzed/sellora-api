<?php

declare(strict_types=1);

namespace App\Tenant\Cart;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Cart\Models\Cart;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes customers' carts they haven't changed for the retention period (180 days), in every store: an abandoned cart is personal data, not a business record.
 *
 * The customer simply starts a new cart next time.
 *
 * @extends TimestampRetentionPolicy<Cart>
 */
final class CustomerCartRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'customer_carts';
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
        return Cart::query()->whereNotNull('customer_id');
    }

    protected function timestampColumn(): string
    {
        return 'last_activity_at';
    }
}
