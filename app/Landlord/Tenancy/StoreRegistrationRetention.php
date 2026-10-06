<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes store sign-ups, which hold an email and a password hash, once their code has been expired for the whole retention period.
 *
 * A sign-up that was never confirmed goes with its legal acceptances: no
 * contract was formed. A confirmed one goes once its store has its owner
 * (the password hash is gone by then); the store's acceptances stay. One
 * whose store is still waiting to be set up is kept, because a retry needs
 * its password hash.
 *
 * @extends TimestampRetentionPolicy<StoreRegistration>
 */
final class StoreRegistrationRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'store_registrations';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Central;
    }

    /**
     * @return Builder<StoreRegistration>
     */
    protected function query(): Builder
    {
        return StoreRegistration::query()->where(static function (Builder $query): void {
            $query->whereNull('verified_at')->orWhereNull('password_hash');
        });
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
