<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use LogicException;

/**
 * Checkout handed over a draft that can't become an order as it stands, such as shipping missing for lines that ship or a payment deadline beyond the maximum. A programming mistake, never the buyer's.
 */
final class InvalidOrderDraftException extends LogicException {}
