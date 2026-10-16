<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when the gateway says the credentials entered do not work. Nothing was saved.
 */
final class PaymentGatewayCredentialsInvalidException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_gateway_credentials_invalid';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['credentials' => [$this->translatedMessage()]];
    }
}
