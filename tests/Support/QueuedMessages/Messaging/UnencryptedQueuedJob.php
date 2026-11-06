<?php

declare(strict_types=1);

namespace Tests\Support\QueuedMessages\Messaging;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A queued job of a Messaging domain that isn't encrypted, for proving the architecture test finds one: Messaging's jobs carry messages.
 */
final class UnencryptedQueuedJob implements ShouldQueue {}
