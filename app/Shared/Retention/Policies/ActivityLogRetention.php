<?php

declare(strict_types=1);

namespace App\Shared\Retention\Policies;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Deletes old entries from each store's activity feed ("Ada cancelled order #1042"), and from the platform's own, which name the people involved.
 *
 * @extends TimestampRetentionPolicy<Activity>
 */
final class ActivityLogRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'activity_log';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Both;
    }

    /**
     * @return Builder<Activity>
     */
    protected function query(): Builder
    {
        return Activity::query();
    }

    protected function timestampColumn(): string
    {
        return 'created_at';
    }
}
