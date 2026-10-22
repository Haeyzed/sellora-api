<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use App\Tenant\Checkout\Data\CheckoutQuote;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when placing an order whose quote still has issues to sort out, such as a missing email or payment method. Nothing was placed; the quote comes with it, listing them.
 */
final class CheckoutNotReadyException extends DomainException
{
    public function __construct(public readonly CheckoutQuote $quote)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'checkout_not_ready';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
