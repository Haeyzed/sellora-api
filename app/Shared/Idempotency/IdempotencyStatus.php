<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

/**
 * Where a request with an Idempotency-Key stands: still running, or finished with a response that can be replayed.
 */
enum IdempotencyStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
}
