<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Throwable;

/**
 * Laravel's store of failed queued jobs, except that the error saved with each one is scrubbed first, as reported errors are.
 *
 * The queue worker saves a failed job's error as it was thrown, never through
 * the exception handler, so without this a database error's SQL and values
 * would sit in failed_jobs.exception until the row is purged.
 */
final class ScrubbedFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /**
     * Saves a failed job with its error scrubbed.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  Throwable  $exception
     */
    public function log($connection, $queue, $payload, $exception): ?string
    {
        // Strictly: the stored text holds every error that caused it too.
        return parent::log($connection, $queue, $payload, ErrorScrubber::scrubForOutput($exception));
    }
}
