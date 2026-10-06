<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Plans\Models\Plan;
use App\Landlord\Tenancy\Exceptions\StoreRegistrationClosedException;

/**
 * What must exist before anyone can register a store: the plan new stores start on, and a version in force of every legal document.
 */
final readonly class StoreRegistrationRequirements
{
    /**
     * The plan every new store starts on, from config/platform.php.
     *
     * @throws StoreRegistrationClosedException When it doesn't exist or has been retired.
     */
    public function startingPlan(): Plan
    {
        $plan = Plan::query()
            ->where('code', config()->string('platform.store_registration.plan'))
            ->where('is_active', true)
            ->first();

        return $plan ?? throw new StoreRegistrationClosedException;
    }

    /**
     * The version in force of every legal document, which merchants must accept.
     *
     * @return array<string, LegalDocument> Keyed by type.
     *
     * @throws StoreRegistrationClosedException When a type has no version in force yet.
     */
    public function legalDocumentsToAccept(): array
    {
        $inForce = LegalDocument::inForce();

        if (count($inForce) !== count(LegalDocumentType::cases())) {
            throw new StoreRegistrationClosedException;
        }

        return $inForce;
    }
}
