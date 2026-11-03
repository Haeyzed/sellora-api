<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use PDOException;
use Throwable;

/**
 * Takes personal data out of errors before they reach logs, error tracking, console output or failed jobs.
 *
 * A database error's message holds the SQL with its values and PostgreSQL's
 * "Failing row" detail, either of which can quote a buyer's email or address.
 * From a database error only its SQLSTATE and the name of the constraint it
 * broke are kept. Code that catches a database error to handle it still sees
 * the original: errors are scrubbed only where they are reported or shown.
 */
final class ErrorScrubber
{
    /**
     * The error as the exception handler may report or show it: unchanged, unless it or an error that caused it is a database error, which is replaced by one without its details and with no link back to it.
     *
     * A business rule error is left as it is, even when a database error
     * caused it (a duplicate name, for example): it is never reported, and it
     * is shown only by its own code and translated message.
     */
    public static function scrub(Throwable $exception): Throwable
    {
        return $exception instanceof DomainException ? $exception : self::scrubForOutput($exception);
    }

    /**
     * The error as it may be printed or stored together with the errors that caused it, as console output and failed jobs are: any error caused by a database error is replaced, a business rule error included.
     */
    public static function scrubForOutput(Throwable $exception): Throwable
    {
        $databaseError = self::databaseErrorIn($exception);

        if ($databaseError === null) {
            return $exception;
        }

        return new ScrubbedDatabaseException($exception, self::describeDatabaseError($databaseError));
    }

    /**
     * What went wrong, without personal data, for places that print or store a short reason: a business rule error's code, a database error's SQLSTATE and the constraint it broke, otherwise only the error's class.
     */
    public static function describe(Throwable $exception): string
    {
        if ($exception instanceof DomainException) {
            return $exception->errorCode();
        }

        $databaseError = self::databaseErrorIn($exception);

        return $databaseError !== null ? self::describeDatabaseError($databaseError) : $exception::class;
    }

    /**
     * The same description, from only an error's text, for places that no longer have the error itself (such as an error tracker's event): keeps the SQLSTATE and the constraint's name, drops the rest.
     */
    public static function describeDatabaseErrorText(string $message): string
    {
        $sqlState = preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/', $message, $matches) === 1 ? $matches[1] : 'unknown';

        return "database error SQLSTATE[{$sqlState}]".self::constraintIn($message);
    }

    /**
     * Whether an error's text looks like a database error's, which quotes SQL and its values.
     */
    public static function looksLikeDatabaseErrorText(string $message): bool
    {
        return str_contains($message, 'SQLSTATE[');
    }

    /**
     * The error itself or the first error that caused it that is a database error (Laravel's QueryException is a PDOException too).
     */
    private static function databaseErrorIn(Throwable $exception): ?PDOException
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof PDOException) {
                return $current;
            }
        }

        return null;
    }

    private static function describeDatabaseError(PDOException $exception): string
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        // Only the constraint's name: the rest of the driver's message can quote values.
        $driverMessage = (string) ($exception->errorInfo[2] ?? '');

        return "database error SQLSTATE[{$sqlState}]".self::constraintIn($driverMessage);
    }

    private static function constraintIn(string $message): string
    {
        return preg_match('/constraint "([A-Za-z0-9_]+)"/', $message, $matches) === 1 ? " on constraint {$matches[1]}" : '';
    }
}
