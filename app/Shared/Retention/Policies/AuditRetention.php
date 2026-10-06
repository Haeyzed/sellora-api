<?php

declare(strict_types=1);

namespace App\Shared\Retention\Policies;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Models\Audit;

/**
 * Deletes old field-level change history (old value → new value) in each store, which records who changed what.
 *
 * @extends TimestampRetentionPolicy<Audit>
 */
final class AuditRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'audits';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<Audit>
     */
    protected function query(): Builder
    {
        return Audit::query();
    }

    protected function timestampColumn(): string
    {
        return 'created_at';
    }
}
