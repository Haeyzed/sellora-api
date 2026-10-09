<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a product already has as many variants outside the trash as a product may have (config/catalog.php).
 */
final class VariantLimitReachedException extends DomainException
{
    public function __construct(int $limit)
    {
        parent::__construct(['limit' => $limit]);
    }

    public function errorCode(): string
    {
        return 'variant_limit_reached';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
