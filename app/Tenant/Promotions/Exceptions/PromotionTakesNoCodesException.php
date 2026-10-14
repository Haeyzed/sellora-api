<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when adding codes to an automatic promotion, which applies without one.
 */
final class PromotionTakesNoCodesException extends DomainException
{
    public function errorCode(): string
    {
        return 'promotion_takes_no_codes';
    }
}
