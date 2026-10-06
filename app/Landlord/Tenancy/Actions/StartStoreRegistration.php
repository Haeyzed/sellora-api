<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Legal\Exceptions\LegalDocumentsNotAcceptedException;
use App\Landlord\Legal\Models\LegalAcceptance;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Tenancy\Data\StoreRegistrationData;
use App\Landlord\Tenancy\Exceptions\HostingRegionUnavailableException;
use App\Landlord\Tenancy\Exceptions\StoreRegistrationClosedException;
use App\Landlord\Tenancy\Exceptions\StoresPerEmailLimitReachedException;
use App\Landlord\Tenancy\Exceptions\SubdomainTakenException;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Landlord\Tenancy\Services\DatabaseServerPlacement;
use App\Landlord\Tenancy\Services\StoreRegistrationCodes;
use App\Landlord\Tenancy\Services\StoreRegistrationRequirements;
use App\Landlord\Tenancy\Services\StoresPerEmailLimit;
use App\Landlord\Tenancy\Services\StoreSubdomains;
use App\Landlord\Tenancy\StoreRegistrationCodeNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * The first step of registering a store: records the sign-up and the legal documents accepted, and emails a code to confirm the email.
 *
 * Nothing is created for the store yet, so a sign-up that is never confirmed
 * costs one row, not a database. The subdomain is held for the sign-up until
 * its code expires.
 */
final readonly class StartStoreRegistration
{
    public function __construct(
        private StoreRegistrationRequirements $storeRegistrationRequirements,
        private DatabaseServerPlacement $databaseServerPlacement,
        private StoreSubdomains $storeSubdomains,
        private StoresPerEmailLimit $storesPerEmailLimit,
        private StoreRegistrationCodes $storeRegistrationCodes,
    ) {}

    /**
     * @throws StoreRegistrationClosedException When the starting plan or a legal document in force is missing.
     * @throws LegalDocumentsNotAcceptedException When the accepted documents aren't exactly the ones in force.
     * @throws HostingRegionUnavailableException When no server in the region accepts new stores.
     * @throws SubdomainTakenException When another store or sign-up has the subdomain.
     * @throws StoresPerEmailLimitReachedException When the email can't have another store.
     */
    public function handle(StoreRegistrationData $data): StoreRegistration
    {
        $this->storeRegistrationRequirements->startingPlan();
        $legalDocuments = $this->storeRegistrationRequirements->legalDocumentsToAccept();
        $this->ensureAcceptsAll($legalDocuments, $data->acceptedLegalDocumentIds);

        if (! $this->databaseServerPlacement->hasRoomIn($data->hostingRegion)) {
            throw new HostingRegionUnavailableException;
        }

        $code = $this->storeRegistrationCodes->generate();
        $now = CarbonImmutable::now();

        $storeRegistration = StoreRegistration::query()->getConnection()->transaction(function () use ($data, $legalDocuments, $code, $now): StoreRegistration {
            $this->storesPerEmailLimit->ensureRoomForOneMore($data->email);
            $this->storeSubdomains->ensureFree($data->subdomain);

            $storeRegistration = new StoreRegistration([
                'store_name' => $data->storeName,
                'subdomain' => $data->subdomain,
                'owner_name' => $data->ownerName,
                'email' => $data->email,
                'country_code' => $data->countryCode,
                'timezone' => $data->timezone,
                'hosting_region' => $data->hostingRegion,
            ]);
            $storeRegistration->forceFill([
                'password_hash' => Hash::make($data->password),
                'verification_code_hash' => $this->storeRegistrationCodes->hash($code),
                'verification_code_sent_at' => $now,
                'expires_at' => $now->addMinutes(config()->integer('platform.store_registration.verification_code_expire_minutes')),
            ])->save();

            foreach ($legalDocuments as $legalDocument) {
                LegalAcceptance::query()->create([
                    'legal_document_id' => $legalDocument->id,
                    'store_registration_id' => $storeRegistration->id,
                    'accepted_by_name' => $data->ownerName,
                    'accepted_by_email' => $data->email,
                    'ip_address' => $data->ipAddress,
                    'user_agent' => $data->userAgent === null ? null : mb_substr($data->userAgent, 0, 512),
                    'accepted_at' => $now,
                ]);
            }

            return $storeRegistration;
        });

        Notification::route('mail', $data->email)->notify(new StoreRegistrationCodeNotification(
            $code,
            $data->storeName,
            config()->integer('platform.store_registration.verification_code_expire_minutes'),
        ));

        return $storeRegistration;
    }

    /**
     * The merchant must accept exactly the versions in force, so nobody signs up having seen a version that was replaced while the form was open.
     *
     * @param  array<string, LegalDocument>  $legalDocuments
     * @param  list<string>  $acceptedLegalDocumentIds
     *
     * @throws LegalDocumentsNotAcceptedException
     */
    private function ensureAcceptsAll(array $legalDocuments, array $acceptedLegalDocumentIds): void
    {
        $required = array_map(static fn (LegalDocument $legalDocument): string => $legalDocument->public_id, array_values($legalDocuments));
        $accepted = array_values(array_unique($acceptedLegalDocumentIds));

        sort($required);
        sort($accepted);

        if ($required !== $accepted) {
            throw new LegalDocumentsNotAcceptedException;
        }
    }
}
