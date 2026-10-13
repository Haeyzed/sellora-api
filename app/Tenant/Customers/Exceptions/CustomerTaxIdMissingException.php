<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when staff make a customer tax-exempt who has entered no tax ID, which an exemption must rest on.
 */
final class CustomerTaxIdMissingException extends DomainException
{
    public function errorCode(): string
    {
        return 'customer_tax_id_missing';
    }
}
