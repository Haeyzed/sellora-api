<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use RuntimeException;

/**
 * Undoes the transaction placing an order when the quote confirmed no longer stands, so PlaceCheckoutOrder can save and return the new one outside it. Never leaves PlaceCheckoutOrder: callers get CheckoutQuoteChangedException.
 *
 * @internal
 */
final class QuoteNoLongerStands extends RuntimeException {}
