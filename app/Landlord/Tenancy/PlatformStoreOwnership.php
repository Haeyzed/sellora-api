<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalAcceptance;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreOwnership;
use App\Shared\Tenancy\Exceptions\TermsOfServiceNotAcceptedException;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Keeps the platform's record of who owns a store: the owner contact on the store's row, and the new owner's acceptance of the terms of service.
 *
 * Called by Tenant\Identity when an ownership transfer is accepted. The
 * acceptance is kept as evidence that the contract moved to the new owner;
 * the transfer itself goes in the store's own activity log.
 */
final readonly class PlatformStoreOwnership implements StoreOwnership
{
    /**
     * @throws LogicException When no store is current, which is a programming mistake.
     */
    public function recordNewOwner(string $name, string $email, string $acceptedTermsOfServiceId, ?string $ipAddress, ?string $userAgent): void
    {
        $store = tenant();

        if (! $store instanceof Tenant) {
            throw new LogicException('A new owner can only be recorded while a store is current.');
        }

        Tenant::query()->getConnection()->transaction(static function () use ($store, $name, $email, $acceptedTermsOfServiceId, $ipAddress, $userAgent): void {
            $termsOfService = LegalDocument::query()->inForceFor(LegalDocumentType::TermsOfService)->first();

            if ($termsOfService === null || $termsOfService->public_id !== $acceptedTermsOfServiceId) {
                throw new TermsOfServiceNotAcceptedException;
            }

            $lockedStore = Tenant::query()->whereKey($store->getTenantKey())->lockForUpdate()->firstOrFail();
            $lockedStore->forceFill(['owner_name' => $name, 'owner_email' => $email])->save();

            $acceptance = new LegalAcceptance([
                'legal_document_id' => $termsOfService->id,
                'accepted_by_name' => $name,
                'accepted_by_email' => $email,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
                'accepted_at' => CarbonImmutable::now(),
            ]);
            $acceptance->forceFill(['tenant_id' => $lockedStore->id])->save();
        });
    }
}
