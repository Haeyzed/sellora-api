<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another code in the store is the same, ignoring case.
 */
final class PromotionCodeTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'promotion_code_taken';
    }

    public function fieldErrors(): array
    {
        return ['code' => [$this->translatedMessage()]];
    }
}
