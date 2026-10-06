<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a sign-up doesn't accept exactly the legal documents in force, for example because a new version was published while the form was open.
 */
final class LegalDocumentsNotAcceptedException extends DomainException
{
    public function errorCode(): string
    {
        return 'legal_documents_not_accepted';
    }

    public function fieldErrors(): array
    {
        return ['accepted_legal_documents' => [$this->translatedMessage()]];
    }
}
