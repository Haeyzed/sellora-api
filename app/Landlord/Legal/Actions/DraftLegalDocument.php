<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Exceptions\LegalDocumentVersionTakenException;
use App\Landlord\Legal\Models\LegalDocument;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Starts a new version of a legal document as a draft. Merchants don't see it until it is published.
 */
final readonly class DraftLegalDocument
{
    /**
     * @throws LegalDocumentVersionTakenException When the document already has a version with this label.
     */
    public function handle(PlatformAdmin $actor, LegalDocumentType $type, string $version, string $title, string $body): LegalDocument
    {
        try {
            // Its own transaction, so a clash rolls back only this insert when called inside a larger transaction.
            $legalDocument = LegalDocument::query()->getConnection()->transaction(static fn (): LegalDocument => LegalDocument::query()->create([
                'type' => $type,
                'version' => $version,
                'title' => $title,
                'body' => $body,
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            throw new LegalDocumentVersionTakenException(previous: $exception);
        }

        activity('legal')
            ->causedBy($actor)
            ->performedOn($legalDocument)
            ->event('legal_document_drafted')
            ->log("Drafted version {$version} of the {$type->value}");

        return $legalDocument;
    }
}
