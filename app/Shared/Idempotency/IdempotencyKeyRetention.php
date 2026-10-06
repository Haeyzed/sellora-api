<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes idempotency records once their replay window has passed, on the platform and in every store.
 *
 * The stored responses can contain personal data, so they are not kept
 * longer than retries need them.
 *
 * @extends TimestampRetentionPolicy<IdempotencyKey>
 */
final class IdempotencyKeyRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'idempotency_keys';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Both;
    }

    /**
     * @return Builder<IdempotencyKey>
     */
    protected function query(): Builder
    {
        return IdempotencyKey::query();
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
