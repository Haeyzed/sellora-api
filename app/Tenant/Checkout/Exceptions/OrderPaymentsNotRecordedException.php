<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\ErrorScrubber;
use RuntimeException;
use Throwable;

/**
 * Reported when recording an order's payments failed after some of its payment holds settled: what the error tracker is told instead of the error itself.
 *
 * It names the order and holds by public ID and says what went wrong without
 * personal data: a database error's SQLSTATE and the constraint it broke, if
 * any (never its message, SQL, values or the failing row, which can hold the
 * buyer's details), a domain error's code, otherwise only the error's class.
 * It keeps no link to the original error for the same reason.
 */
final class OrderPaymentsNotRecordedException extends RuntimeException
{
    /**
     * @param  list<string>  $holds  The public IDs of the holds it followed.
     */
    public function __construct(public readonly string $order, public readonly array $holds, public readonly string $reason)
    {
        parent::__construct("Recording the payments of order {$order} failed after its payment holds ".implode(', ', $holds)." settled: {$reason}.");
    }

    /**
     * @param  list<string>  $holds
     */
    public static function after(Throwable $exception, string $order, array $holds): self
    {
        return new self($order, $holds, ErrorScrubber::describe($exception));
    }
}
