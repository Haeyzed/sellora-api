<?php

declare(strict_types=1);

namespace App\Tenant\Delivery;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Delivery\Models\DriverSetupCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes drivers' PIN setup codes once they have been expired for the whole retention period, in every store. Each works once, within hours.
 *
 * @extends TimestampRetentionPolicy<DriverSetupCode>
 */
final class DriverSetupCodeRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'driver_setup_codes';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<DriverSetupCode>
     */
    protected function query(): Builder
    {
        return DriverSetupCode::query();
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
