<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use App\Tenant\Checkout\Data\CheckoutQuote;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when placing an order finds the quote isn't the one the shopper confirmed: it expired, or something charged changed (a price, stock, a promotion, shipping, tax, a payment method, or the exchange rate going stale). Nothing was placed; the new quote comes with it, to show and confirm.
 */
final class CheckoutQuoteChangedException extends DomainException
{
    public function __construct(public readonly CheckoutQuote $quote)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'checkout_quote_changed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
