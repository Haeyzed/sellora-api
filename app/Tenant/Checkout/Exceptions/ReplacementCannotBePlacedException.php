<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use App\Tenant\Checkout\Data\ReplacementQuote;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a replacement order can't be placed now: a variant is no longer for sale or is out of stock, the order's currency isn't offered any more, or its delivery can't be priced. Nothing was placed; the quote says every reason.
 *
 * The code is the first reason: replacement_not_available, replacement_out_of_stock, replacement_currency_not_offered or replacement_shipping_unavailable.
 */
final class ReplacementCannotBePlacedException extends DomainException
{
    public function __construct(public readonly ReplacementQuote $quote)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return $this->quote->issues[0] ?? 'replacement_not_available';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
