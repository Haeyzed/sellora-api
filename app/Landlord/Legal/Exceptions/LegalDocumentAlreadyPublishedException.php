<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a published legal document is changed or published again. Changes need a new version.
 */
final class LegalDocumentAlreadyPublishedException extends DomainException
{
    public function errorCode(): string
    {
        return 'legal_document_already_published';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
