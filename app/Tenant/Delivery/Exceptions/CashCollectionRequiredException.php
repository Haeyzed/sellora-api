<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a delivery with cash to collect is marked delivered without saying how much cash was collected.
 */
final class CashCollectionRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_collection_required';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
