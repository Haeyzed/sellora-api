<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in server, started by recordingServer(): writes each
 * request's URL to the log file named in REQUEST_LOG and answers 404, so a
 * test can see whether anything asked it for something.
 */
file_put_contents((string) getenv('REQUEST_LOG'), ($_SERVER['REQUEST_URI'] ?? '').PHP_EOL, FILE_APPEND | LOCK_EX);
http_response_code(404);
