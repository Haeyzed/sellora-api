<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Legal\Models\LegalAcceptance;
use App\Landlord\Subscriptions\Actions\StartSubscription;
use App\Landlord\Subscriptions\Enums\SubscriptionStatus;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\InvalidStoreRegistrationCodeException;
use App\Landlord\Tenancy\Exceptions\StoreRegistrationClosedException;
use App\Landlord\Tenancy\Exceptions\StoreRegistrationExpiredException;
use App\Landlord\Tenancy\Exceptions\StoresPerEmailLimitReachedException;
use App\Landlord\Tenancy\Exceptions\SubdomainTakenException;
use App\Landlord\Tenancy\Jobs\ProvisionStore;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\CountryDefaults;
use App\Landlord\Tenancy\Services\StoreRegistrationCodes;
use App\Landlord\Tenancy\Services\StoreRegistrationRequirements;
use App\Landlord\Tenancy\Services\StoresPerEmailLimit;
use App\Landlord\Tenancy\Services\StoreSubdomains;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * Registers a store once the merchant enters the code from their email: creates the store with its subdomain and starting plan, then queues setting it up.
 *
 * The store serves no requests until setting up has succeeded. Each code
 * works once, and a wrong code is counted even though the request fails, so
 * codes can't be guessed.
 */
final readonly class RegisterStore
{
    public function __construct(
        private StoreRegistrationCodes $storeRegistrationCodes,
        private StoreRegistrationRequirements $storeRegistrationRequirements,
        private StoresPerEmailLimit $storesPerEmailLimit,
        private StoreSubdomains $storeSubdomains,
        private CountryDefaults $countryDefaults,
        private StartSubscription $startSubscription,
    ) {}

    /**
     * @throws InvalidStoreRegistrationCodeException When the code is wrong, already used, or has had too many wrong tries.
     * @throws StoreRegistrationExpiredException When the code has expired.
     * @throws StoreRegistrationClosedException When the starting plan was retired in the meantime.
     * @throws SubdomainTakenException When the subdomain was taken in the meantime.
     * @throws StoresPerEmailLimitReachedException When the email reached its limit in the meantime.
     */
    public function handle(StoreRegistration $storeRegistration, string $code): Tenant
    {
        $tenant = StoreRegistration::query()->getConnection()->transaction(function () use ($storeRegistration, $code): ?Tenant {
            $locked = StoreRegistration::query()->whereKey($storeRegistration->id)->lockForUpdate()->firstOrFail();

            if ($locked->verified_at !== null || $locked->verification_attempts >= StoreRegistration::MAX_VERIFICATION_ATTEMPTS) {
                throw new InvalidStoreRegistrationCodeException;
            }

            if (! $locked->expires_at->isFuture()) {
                throw new StoreRegistrationExpiredException;
            }

            if (! $this->storeRegistrationCodes->matches($code, $locked->verification_code_hash)) {
                // Returning, not throwing, so the wrong try is committed.
                $locked->increment('verification_attempts');

                return null;
            }

            return $this->createStore($locked);
        });

        if ($tenant === null) {
            throw new InvalidStoreRegistrationCodeException;
        }

        ProvisionStore::dispatch($tenant->id);

        return $tenant;
    }

    private function createStore(StoreRegistration $storeRegistration): Tenant
    {
        $plan = $this->storeRegistrationRequirements->startingPlan();
        $this->storesPerEmailLimit->ensureRoomForOneMore($storeRegistration->email, replacing: $storeRegistration);
        $this->storeSubdomains->ensureFree($storeRegistration->subdomain, except: $storeRegistration);

        $tenant = Tenant::query()->create([
            'name' => $storeRegistration->store_name,
            'status' => TenantStatus::Provisioning,
            'hosting_region' => $storeRegistration->hosting_region,
            'owner_name' => $storeRegistration->owner_name,
            'owner_email' => $storeRegistration->email,
            'country_code' => $storeRegistration->country_code,
            'currency_code' => $this->countryDefaults->currencyCode($storeRegistration->country_code)
                ?? throw new LogicException("The country {$storeRegistration->country_code} has no currency."),
            'timezone' => $storeRegistration->timezone,
            'locale' => config()->string('app.locale'),
        ]);

        try {
            $tenant->domains()->create(['domain' => Tenant::platformDomainFor($storeRegistration->subdomain)]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new SubdomainTakenException(previous: $exception);
        }

        // The acceptances now belong to the store, and outlive the sign-up record.
        LegalAcceptance::query()
            ->where('store_registration_id', $storeRegistration->id)
            ->update(['tenant_id' => $tenant->id, 'store_registration_id' => null]);

        $this->startSubscription->handle($tenant, $plan, SubscriptionStatus::Active);

        $storeRegistration->forceFill(['verified_at' => CarbonImmutable::now(), 'tenant_id' => $tenant->id])->save();

        activity('stores')
            ->performedOn($tenant)
            ->event('store_registered')
            ->withProperties(['hosting_region' => $tenant->hosting_region, 'plan' => $plan->code])
            ->log("Registered the store {$tenant->name}");

        return $tenant;
    }
}
