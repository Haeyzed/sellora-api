<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Customers\Models\SocialSignInAttempt;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes social sign-ins started in a store (Step 10b) once expired for the retention period, in every store: each works for minutes, used or not.
 *
 * @extends TimestampRetentionPolicy<SocialSignInAttempt>
 */
final class SocialSignInAttemptRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'social_sign_in_attempts';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<SocialSignInAttempt>
     */
    protected function query(): Builder
    {
        return SocialSignInAttempt::query();
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
