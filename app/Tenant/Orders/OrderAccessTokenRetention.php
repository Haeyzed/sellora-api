<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Orders\Models\OrderAccessToken;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes guests' order access tokens once they have expired for the retention period, in every store: they open nothing by then.
 *
 * @extends TimestampRetentionPolicy<OrderAccessToken>
 */
final class OrderAccessTokenRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'order_access_tokens';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<OrderAccessToken>
     */
    protected function query(): Builder
    {
        return OrderAccessToken::query();
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
