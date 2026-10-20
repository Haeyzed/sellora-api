<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when downloading an invoice's or credit note's PDF before it has been rendered. It is on its way; try again shortly.
 */
final class TaxDocumentPdfNotReadyException extends DomainException
{
    public function errorCode(): string
    {
        return 'tax_document_pdf_not_ready';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
