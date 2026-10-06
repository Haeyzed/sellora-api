<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Exceptions\LegalDocumentAlreadyPublishedException;
use App\Landlord\Legal\Exceptions\LegalDocumentVersionTakenException;
use App\Landlord\Legal\Models\LegalDocument;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Changes a draft's version label, title or text. A published version never changes; that needs a new version.
 */
final readonly class ReviseLegalDocument
{
    /**
     * @throws LegalDocumentAlreadyPublishedException When the version is already published.
     * @throws LegalDocumentVersionTakenException When another version of the document has the new label.
     */
    public function handle(PlatformAdmin $actor, LegalDocument $legalDocument, string $version, string $title, string $body): LegalDocument
    {
        return LegalDocument::query()->getConnection()->transaction(static function () use ($actor, $legalDocument, $version, $title, $body): LegalDocument {
            $locked = LegalDocument::query()->whereKey($legalDocument->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPublished()) {
                throw new LegalDocumentAlreadyPublishedException;
            }

            try {
                $locked->update(['version' => $version, 'title' => $title, 'body' => $body]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new LegalDocumentVersionTakenException(previous: $exception);
            }

            activity('legal')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('legal_document_revised')
                ->log("Revised the draft {$locked->version} of the {$locked->type->value}");

            return $locked;
        });
    }
}
