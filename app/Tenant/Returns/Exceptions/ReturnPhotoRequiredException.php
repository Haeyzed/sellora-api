<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the store requires a photo for damage or the wrong item and a buyer gave that reason without one.
 */
final class ReturnPhotoRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'return_photo_required';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
