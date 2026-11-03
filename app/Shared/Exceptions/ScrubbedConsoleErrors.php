<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Illuminate\Foundation\Exceptions\Handler;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * The application's exception handler: Laravel's own, except that an error a console command didn't catch is scrubbed before it is printed, as it is before it is reported.
 *
 * Laravel maps errors before reporting and rendering them, but not before
 * printing them in the console, so a scheduled command that crashed on a
 * database error would print its SQL and values to the terminal and the
 * scheduler's output. Laravel marks this method internal; a test runs a
 * crashing command through the console kernel, so an upgrade that changes
 * how errors reach the console fails it.
 */
final class ScrubbedConsoleErrors extends Handler
{
    /**
     * Prints an error a console command didn't catch, without a database error's details.
     *
     * @param  OutputInterface  $output
     */
    public function renderForConsole($output, Throwable $e): void
    {
        // Strictly: the console prints every error that caused it too, so a business rule error caused by a database error is scrubbed as well.
        parent::renderForConsole($output, ErrorScrubber::scrubForOutput($e));
    }
}
