<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalAcceptance;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreOwnerContact;
use App\Shared\Tenancy\Exceptions\TermsOfServiceNotAcceptedException;
use App\Shared\Tenancy\TermsOfServiceAcceptance;
use LogicException;

/**
 * Keeps the platform's record of who owns a store: the owner contact on the store's row, and the owners' acceptances of the terms of service.
 *
 * Called from the store's side once its own change has committed. The
 * acceptance is kept as evidence that the contract moved to the new owner.
 */
final readonly class PlatformStoreOwnerContact implements StoreOwnerContact
{
    public function ensureTermsOfServiceInForce(string $termsOfServiceId): void
    {
        $termsOfService = LegalDocument::query()->inForceFor(LegalDocumentType::TermsOfService)->first();

        if ($termsOfService === null || $termsOfService->public_id !== $termsOfServiceId) {
            throw new TermsOfServiceNotAcceptedException;
        }
    }

    /**
     * @throws LogicException When no store is current, which is a programming mistake.
     */
    public function update(string $name, string $email, ?TermsOfServiceAcceptance $acceptance = null): void
    {
        $store = tenant();

        if (! $store instanceof Tenant) {
            throw new LogicException('A store\'s owner contact can only be updated while that store is current.');
        }

        Tenant::query()->getConnection()->transaction(static function () use ($store, $name, $email, $acceptance): void {
            $lockedStore = Tenant::query()->whereKey($store->getTenantKey())->lockForUpdate()->firstOrFail();

            // A purge clears the owner's details for good; a late update must not bring them back.
            if (in_array($lockedStore->status, [TenantStatus::Purging, TenantStatus::Purged], true)) {
                return;
            }

            $lockedStore->forceFill(['owner_name' => $name, 'owner_email' => $email])->save();

            if ($acceptance !== null) {
                self::recordAcceptance($lockedStore, $acceptance);
            }
        });
    }

    /**
     * Records the acceptance once: the store row is locked, so a second call sees the first one's record.
     */
    private static function recordAcceptance(Tenant $store, TermsOfServiceAcceptance $acceptance): void
    {
        $alreadyRecorded = LegalAcceptance::query()
            ->where('tenant_id', $store->id)
            ->where('reference', $acceptance->reference)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        // The version accepted, even if a newer one has taken effect since: it was in force when they accepted.
        $termsOfService = LegalDocument::query()->where('public_id', $acceptance->termsOfServiceId)->firstOrFail();

        $legalAcceptance = new LegalAcceptance([
            'legal_document_id' => $termsOfService->id,
            'accepted_by_name' => $acceptance->name,
            'accepted_by_email' => $acceptance->email,
            'ip_address' => $acceptance->ipAddress,
            'user_agent' => $acceptance->userAgent === null ? null : mb_substr($acceptance->userAgent, 0, 512),
            'accepted_at' => $acceptance->acceptedAt,
        ]);
        $legalAcceptance->forceFill(['tenant_id' => $store->id, 'reference' => $acceptance->reference])->save();
    }
}
