<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The gateway timed out or answered with a server error: the outcome of the call is unknown, never a failure. Whatever was asked is checked again later.
 */
final class GatewayUnavailableException extends RuntimeException
{
    public function __construct(string $gateway, string $operation, ?Throwable $previous = null)
    {
        parent::__construct("The {$gateway} gateway didn't answer {$operation}.", 0, $previous);
    }
}
