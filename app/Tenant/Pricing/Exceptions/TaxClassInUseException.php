<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when deleting a tax class that is still used: by a variant (even one in the trash), by a tax rate, as the default class, or for shipping.
 */
final class TaxClassInUseException extends DomainException
{
    public function errorCode(): string
    {
        return 'tax_class_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
