<?php

declare(strict_types=1);

namespace App\Shared\Geography\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a country code isn't an ISO 3166-1 alpha-2 code of a country we support.
 */
final class CountryNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'country_not_found';
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
