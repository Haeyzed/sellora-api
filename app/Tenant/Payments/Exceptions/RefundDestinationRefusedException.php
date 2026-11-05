<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown by a module's refund destination when it definitely won't pay a refund out, so nothing was paid: the refund fails, and the customer can choose again where it goes.
 */
final class RefundDestinationRefusedException extends DomainException
{
    public function errorCode(): string
    {
        return 'refund_destination_refused';
    }
}
