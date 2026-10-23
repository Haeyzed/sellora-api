<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds to a replayed response what must never be stored for replay, such as a secret the original response carried in a header (section 11). Registered per route in ReplayPreparers.
 *
 * Only ever called for the requester who sent the original request: replays
 * are scoped to them.
 */
interface ReplayPreparer
{
    public function prepare(Request $request, Response $replayed): void;
}
