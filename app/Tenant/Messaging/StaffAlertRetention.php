<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Messaging\Models\StaffAlert;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes staff alerts once they are older than retention.periods.staff_alerts days: by then they have been dealt with, and what they were about keeps its own history.
 *
 * @extends TimestampRetentionPolicy<StaffAlert>
 */
final class StaffAlertRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'staff_alerts';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<StaffAlert>
     */
    protected function query(): Builder
    {
        return StaffAlert::query();
    }

    protected function timestampColumn(): string
    {
        return 'created_at';
    }
}
