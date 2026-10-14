<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another customer group already has the name, ignoring case.
 */
final class CustomerGroupNameTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'customer_group_name_taken';
    }

    public function fieldErrors(): array
    {
        return ['name' => [$this->translatedMessage()]];
    }
}
