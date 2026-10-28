<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a delivery is marked delivered without a photo of the goods or the recipient's signature.
 */
final class ProofOfDeliveryRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'proof_of_delivery_required';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
