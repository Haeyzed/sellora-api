<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreProfile;
use App\Shared\Tenancy\StoreProfileDetails;
use LogicException;

/**
 * The platform's copy of a store's name, country, currency, timezone and language, on the store's row.
 *
 * Platform admins see it in the stores list, and platform emails to the
 * owner use its language. The store's settings are the source of truth.
 */
final readonly class PlatformStoreProfile implements StoreProfile
{
    /**
     * @throws LogicException When no store is current, which is a programming mistake.
     */
    public function current(): StoreProfileDetails
    {
        $store = $this->currentStore();

        return new StoreProfileDetails($store->name, $store->country_code, $store->currency_code, $store->timezone, $store->locale);
    }

    /**
     * @throws LogicException When no store is current, which is a programming mistake.
     */
    public function update(StoreProfileDetails $details): void
    {
        $store = $this->currentStore();

        Tenant::query()->getConnection()->transaction(static function () use ($store, $details): void {
            $lockedStore = Tenant::query()->whereKey($store->getTenantKey())->lockForUpdate()->firstOrFail();

            if (in_array($lockedStore->status, [TenantStatus::Purging, TenantStatus::Purged], true)) {
                return;
            }

            $lockedStore->forceFill([
                'name' => $details->name,
                'country_code' => $details->countryCode,
                'currency_code' => $details->currencyCode,
                'timezone' => $details->timezone,
                'locale' => $details->locale,
            ])->save();
        });
    }

    private function currentStore(): Tenant
    {
        $store = tenant();

        if (! $store instanceof Tenant) {
            throw new LogicException('A store\'s profile can only be read or updated while that store is current.');
        }

        return $store;
    }
}
