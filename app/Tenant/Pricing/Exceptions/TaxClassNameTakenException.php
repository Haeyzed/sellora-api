<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another tax class already has the name, ignoring case.
 */
final class TaxClassNameTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'tax_class_name_taken';
    }

    public function fieldErrors(): array
    {
        return ['name' => [$this->translatedMessage()]];
    }
}
