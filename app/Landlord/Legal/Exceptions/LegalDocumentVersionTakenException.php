<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another version of the same document already has this version label.
 */
final class LegalDocumentVersionTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'legal_document_version_taken';
    }

    public function fieldErrors(): array
    {
        return ['version' => [$this->translatedMessage()]];
    }
}
