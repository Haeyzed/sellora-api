<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Customers\Models\SocialSignInTicket;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes what unfinished social sign-ins still needed from the customer (Step 10b), which holds what the provider said about them, once expired for the retention period, in every store.
 *
 * @extends TimestampRetentionPolicy<SocialSignInTicket>
 */
final class SocialSignInTicketRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'social_sign_in_tickets';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<SocialSignInTicket>
     */
    protected function query(): Builder
    {
        return SocialSignInTicket::query();
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
