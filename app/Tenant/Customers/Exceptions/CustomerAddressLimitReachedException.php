<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a customer who already has the most addresses allowed saves another.
 */
final class CustomerAddressLimitReachedException extends DomainException
{
    public function __construct(int $limit)
    {
        parent::__construct(['limit' => $limit]);
    }

    public function errorCode(): string
    {
        return 'customer_address_limit_reached';
    }
}
