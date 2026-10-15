<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised at checkout when a promotion the basket got can't be used any more: it was used up meanwhile, or the buyer has used it as often as allowed. The basket must be priced again.
 */
final class PromotionUnavailableException extends DomainException
{
    public function errorCode(): string
    {
        return 'promotion_unavailable';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
