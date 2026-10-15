<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when deleting a code a checkout or order has used: orders keep pointing at it. Switch it off instead.
 */
final class PromotionCodeInUseException extends DomainException
{
    public function errorCode(): string
    {
        return 'promotion_code_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
