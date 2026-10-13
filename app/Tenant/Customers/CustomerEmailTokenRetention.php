<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Customers\Models\CustomerEmailToken;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes customers' emailed links, which hold an email address, once they have been expired for the whole retention period, in every store.
 *
 * @extends TimestampRetentionPolicy<CustomerEmailToken>
 */
final class CustomerEmailTokenRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'customer_email_tokens';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<CustomerEmailToken>
     */
    protected function query(): Builder
    {
        return CustomerEmailToken::query();
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
