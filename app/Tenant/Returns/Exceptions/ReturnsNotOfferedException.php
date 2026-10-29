<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a buyer asks for a return but the store takes return requests only from staff: it turned buyer requests off, or set the window to 0 days.
 */
final class ReturnsNotOfferedException extends DomainException
{
    public function errorCode(): string
    {
        return 'returns_not_offered';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
