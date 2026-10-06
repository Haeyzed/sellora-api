<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * The common parent of every business rule error, such as "this order can no longer be cancelled".
 *
 * Each subclass is mapped to the platform's standard JSON error response in bootstrap/app.php.
 */
abstract class DomainException extends RuntimeException {}
