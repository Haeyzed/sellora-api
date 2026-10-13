<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when there isn't enough stock: to reserve a variant that doesn't allow backorders, or to fulfil a reservation from what is on hand.
 *
 * Says which variants fell short and how many were available, so a checkout
 * can tell the customer.
 */
final class InsufficientStockException extends DomainException
{
    /**
     * @param  list<array{variant_id: string, requested: int, available: int}>  $shortfalls
     */
    public function __construct(public readonly array $shortfalls)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'insufficient_stock';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
