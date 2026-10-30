<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use Illuminate\Database\QueryException;
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
        return new self($order, $holds, self::describe($exception));
    }

    /**
     * What went wrong, without personal data.
     */
    public static function describe(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            // Only the constraint's name: the rest of the message can quote values.
            $constraint = preg_match('/constraint "([A-Za-z0-9_]+)"/', (string) ($exception->errorInfo[2] ?? ''), $matches) === 1 ? " on constraint {$matches[1]}" : '';

            return "database error SQLSTATE[{$sqlState}]{$constraint}";
        }

        return $exception instanceof DomainException ? $exception->errorCode() : $exception::class;
    }
}
