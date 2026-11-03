<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;

/**
 * The last check before an error report leaves for the error tracker: anything that still reads like a database error loses its SQL and values, and no stack frame carries a call's arguments.
 *
 * Errors are scrubbed before they are reported (ErrorScrubber), and stack
 * traces record no arguments; this catches whatever reaches the tracker some
 * other way, such as an error captured directly or a log line kept as a
 * breadcrumb. Set as Sentry's before_send in config/sentry.php.
 */
final class SentryEventScrubber
{
    /**
     * Scrubs an event in place and lets it be sent.
     */
    public static function scrub(Event $event): Event
    {
        foreach ($event->getExceptions() as $exception) {
            self::scrubException($exception);
        }

        $event->setBreadcrumb(array_map(self::scrubBreadcrumb(...), $event->getBreadcrumbs()));

        return $event;
    }

    private static function scrubException(ExceptionDataBag $exception): void
    {
        // One scrubbed before it was reported already says only what may be said.
        if ($exception->getType() !== ScrubbedDatabaseException::class && ErrorScrubber::looksLikeDatabaseErrorText($exception->getValue())) {
            $exception->setValue(ErrorScrubber::describeDatabaseErrorText($exception->getValue()));
        }

        foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
            $frame->setVars([]);
        }
    }

    private static function scrubBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        $message = $breadcrumb->getMessage();

        if ($message === null || ! ErrorScrubber::looksLikeDatabaseErrorText($message)) {
            return $breadcrumb;
        }

        return $breadcrumb->withMessage(ErrorScrubber::describeDatabaseErrorText($message));
    }
}
