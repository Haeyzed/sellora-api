<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use RuntimeException;

/**
 * The gateway refused the call for good, such as an invalid amount: nothing happened there.
 */
final class GatewayRejectedException extends RuntimeException
{
    /**
     * @param  string|null  $failureCode  The gateway's own short reason, safe to store (never personal data).
     */
    public function __construct(string $gateway, string $operation, public readonly ?string $failureCode = null)
    {
        parent::__construct("The {$gateway} gateway refused {$operation}.");
    }
}
