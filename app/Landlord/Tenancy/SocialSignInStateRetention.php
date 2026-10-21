<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Models\SocialSignInState;
use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes the central relay's routing for customer social sign-ins (Step 10b) once expired for the retention period: a state works for minutes, and is useless after.
 *
 * @extends TimestampRetentionPolicy<SocialSignInState>
 */
final class SocialSignInStateRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'social_sign_in_states';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Central;
    }

    /**
     * @return Builder<SocialSignInState>
     */
    protected function query(): Builder
    {
        return SocialSignInState::query();
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
