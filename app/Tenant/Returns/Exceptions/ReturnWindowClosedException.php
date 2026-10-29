<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a buyer asks to return units whose delivery was longer ago than the store's return window allows. Staff can still start the return, giving a reason.
 */
final class ReturnWindowClosedException extends DomainException
{
    public function errorCode(): string
    {
        return 'return_window_closed';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
