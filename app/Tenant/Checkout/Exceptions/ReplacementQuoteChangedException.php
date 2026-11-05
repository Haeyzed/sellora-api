<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use App\Tenant\Checkout\Data\ReplacementQuote;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a replacement order isn't placed because it no longer comes to what staff were shown: a price, a promotion, shipping, tax or the exchange rate changed since. Nothing was placed; the new quote comes with it, to show and confirm.
 */
final class ReplacementQuoteChangedException extends DomainException
{
    public function __construct(public readonly ReplacementQuote $quote)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'replacement_quote_changed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
