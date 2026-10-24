<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The gateway timed out or answered with a server error: the outcome of the call is unknown, never a failure. Whatever was asked is checked again later.
 *
 * So is a call the gateway refused because of the store's keys (invalid or
 * revoked: keysRefused): nothing was carried out, nothing is known about the
 * payment or refund asked about, and the merchant is to reconnect.
 */
final class GatewayUnavailableException extends RuntimeException
{
    public function __construct(string $gateway, string $operation, ?Throwable $previous = null, public readonly bool $keysRefused = false)
    {
        parent::__construct($keysRefused ? "The {$gateway} gateway refused the store's credentials for {$operation}." : "The {$gateway} gateway didn't answer {$operation}.", 0, $previous);
    }

    public static function keysRefused(string $gateway, string $operation): self
    {
        return new self($gateway, $operation, keysRefused: true);
    }
}
