<?php

declare(strict_types=1);

namespace App\Shared\Retention\Policies;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Deletes sign-in tokens that stopped working a while ago, on the platform and in every store.
 *
 * Tokens expire a fixed time after they are issued (config/sanctum.php), so a
 * token is purged once it has been expired for longer than its retention period.
 *
 * @extends TimestampRetentionPolicy<PersonalAccessToken>
 */
final class ExpiredAccessTokenRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'expired_tokens';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Both;
    }

    /**
     * Moves the cutoff back by the token lifetime, so only tokens that have been expired for the whole retention period go.
     */
    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        return parent::purgeOlderThan($cutoff->subMinutes(config()->integer('sanctum.expiration')));
    }

    /**
     * @return Builder<PersonalAccessToken>
     */
    protected function query(): Builder
    {
        return PersonalAccessToken::query();
    }

    protected function timestampColumn(): string
    {
        return 'created_at';
    }
}
