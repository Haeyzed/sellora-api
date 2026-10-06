<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Tenancy\Exceptions\StoreRegistrationAlreadyVerifiedException;
use App\Landlord\Tenancy\Exceptions\StoresPerEmailLimitReachedException;
use App\Landlord\Tenancy\Exceptions\SubdomainTakenException;
use App\Landlord\Tenancy\Exceptions\VerificationCodeRecentlySentException;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Landlord\Tenancy\Services\StoreRegistrationCodes;
use App\Landlord\Tenancy\Services\StoresPerEmailLimit;
use App\Landlord\Tenancy\Services\StoreSubdomains;
use App\Landlord\Tenancy\StoreRegistrationCodeNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Emails a new code for a sign-up, for example when the first one expired or had too many wrong tries.
 *
 * The previous code stops working, the count of wrong tries starts again, and
 * the sign-up gets a new expiry. At most one code a minute.
 */
final readonly class ResendStoreRegistrationCode
{
    private const int MINIMUM_SECONDS_BETWEEN_CODES = 60;

    public function __construct(
        private StoreRegistrationCodes $storeRegistrationCodes,
        private StoreSubdomains $storeSubdomains,
        private StoresPerEmailLimit $storesPerEmailLimit,
    ) {}

    /**
     * @throws StoreRegistrationAlreadyVerifiedException When the sign-up has already become a store.
     * @throws VerificationCodeRecentlySentException When the last code was sent less than a minute ago.
     * @throws SubdomainTakenException When the sign-up expired and someone else took its subdomain since.
     * @throws StoresPerEmailLimitReachedException When the sign-up expired and the email reached its limit since.
     */
    public function handle(StoreRegistration $storeRegistration): StoreRegistration
    {
        $code = $this->storeRegistrationCodes->generate();

        $storeRegistration = StoreRegistration::query()->getConnection()->transaction(function () use ($storeRegistration, $code): StoreRegistration {
            $locked = StoreRegistration::query()->whereKey($storeRegistration->id)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now();

            if ($locked->verified_at !== null) {
                throw new StoreRegistrationAlreadyVerifiedException;
            }

            if ($locked->verification_code_sent_at->addSeconds(self::MINIMUM_SECONDS_BETWEEN_CODES)->isFuture()) {
                throw new VerificationCodeRecentlySentException;
            }

            // An expired sign-up held nothing in the meantime, so it has to qualify again.
            if (! $locked->isAwaitingVerification()) {
                $this->storesPerEmailLimit->ensureRoomForOneMore($locked->email);
                $this->storeSubdomains->ensureFree($locked->subdomain, except: $locked);
            }

            $locked->forceFill([
                'verification_code_hash' => $this->storeRegistrationCodes->hash($code),
                'verification_attempts' => 0,
                'verification_code_sent_at' => $now,
                'expires_at' => $now->addMinutes(config()->integer('platform.store_registration.verification_code_expire_minutes')),
            ])->save();

            return $locked;
        });

        Notification::route('mail', $storeRegistration->email)->notify(new StoreRegistrationCodeNotification(
            $code,
            $storeRegistration->store_name,
            config()->integer('platform.store_registration.verification_code_expire_minutes'),
        ));

        return $storeRegistration;
    }
}
