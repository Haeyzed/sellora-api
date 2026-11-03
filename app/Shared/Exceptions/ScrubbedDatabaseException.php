<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use PDOException;
use RuntimeException;
use Throwable;

/**
 * What logs, error tracking and failed jobs are told about a database error instead of the error itself, which can quote a buyer's details.
 *
 * It says where the error happened and which SQLSTATE and constraint it
 * broke, never its message, SQL, values or failing row. It keeps no link to
 * the original error, so nothing that prints a chain of errors can reach it.
 */
final class ScrubbedDatabaseException extends RuntimeException
{
    /**
     * @var list<string> Where the original error came from, frame by frame, without the arguments each call was given.
     */
    private readonly array $originFrames;

    public function __construct(Throwable $original, string $description)
    {
        // An error that wraps a database error may quote its message, so only its class is kept.
        // get_debug_type(): an anonymous class's own name holds a NUL byte and a file path.
        parent::__construct($original instanceof PDOException ? $description : get_debug_type($original)." caused by {$description}");

        $this->file = $original->getFile();
        $this->line = $original->getLine();
        $this->originFrames = self::framesOf($original);
    }

    /**
     * Added to the log entry when Laravel reports this error, so the original's path through the code isn't lost.
     *
     * @return array{origin: list<string>}
     */
    public function context(): array
    {
        return ['origin' => $this->originFrames];
    }

    /**
     * @return list<string>
     */
    private static function framesOf(Throwable $original): array
    {
        $frames = [];

        foreach ($original->getTrace() as $frame) {
            $location = isset($frame['file']) ? $frame['file'].':'.($frame['line'] ?? 0) : '[internal]';
            $frames[] = $location.' '.(isset($frame['class']) ? $frame['class'].($frame['type'] ?? '::') : '').$frame['function'];
        }

        return $frames;
    }
}
