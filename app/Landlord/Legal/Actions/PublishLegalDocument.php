<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Exceptions\LegalDocumentAlreadyPublishedException;
use App\Landlord\Legal\Models\LegalDocument;
use Carbon\CarbonImmutable;

/**
 * Publishes a draft, to take effect now or on a later date. From then on it can't change.
 *
 * The version in force stays in force until the new one's effective date,
 * so publishing ahead of time never leaves a gap with no terms in force.
 */
final readonly class PublishLegalDocument
{
    /**
     * @param  CarbonImmutable|null  $effectiveAt  When it takes effect; now if not given. Never in the past.
     *
     * @throws LegalDocumentAlreadyPublishedException When it is already published.
     */
    public function handle(PlatformAdmin $actor, LegalDocument $legalDocument, ?CarbonImmutable $effectiveAt = null): LegalDocument
    {
        return LegalDocument::query()->getConnection()->transaction(static function () use ($actor, $legalDocument, $effectiveAt): LegalDocument {
            $locked = LegalDocument::query()->whereKey($legalDocument->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPublished()) {
                throw new LegalDocumentAlreadyPublishedException;
            }

            $now = CarbonImmutable::now();
            $effectiveAt = $effectiveAt === null || $effectiveAt->isBefore($now) ? $now : $effectiveAt;

            $locked->forceFill(['published_at' => $now, 'effective_at' => $effectiveAt])->save();

            activity('legal')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('legal_document_published')
                ->withProperties(['effective_at' => $effectiveAt->toIso8601String()])
                ->log("Published version {$locked->version} of the {$locked->type->value}");

            return $locked;
        });
    }
}
