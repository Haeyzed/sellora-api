<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when an order paid on a gateway's page is placed without the storefront page the gateway should send the buyer back to.
 */
final class ReturnUrlRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'return_url_required';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['return_url' => [$this->translatedMessage()]];
    }
}
