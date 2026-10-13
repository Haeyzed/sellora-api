<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the tax class already has a rate for the region (the same country and state, or the same country-wide rate). Change that rate instead.
 */
final class TaxRateExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'tax_rate_exists';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
