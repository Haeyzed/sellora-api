<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when adding a currency the store already has, even an inactive one. Change that one instead.
 */
final class StoreCurrencyExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_currency_exists';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
