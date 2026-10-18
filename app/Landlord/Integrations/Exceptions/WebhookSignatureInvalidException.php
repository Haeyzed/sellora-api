<?php

declare(strict_types=1);

namespace App\Landlord\Integrations\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a webhook isn't signed by the provider for the store's account: nothing in it was read or kept.
 */
final class WebhookSignatureInvalidException extends DomainException
{
    public function errorCode(): string
    {
        return 'webhook_signature_invalid';
    }

    public function status(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
