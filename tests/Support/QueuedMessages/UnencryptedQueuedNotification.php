<?php

declare(strict_types=1);

namespace Tests\Support\QueuedMessages;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A queued notification that isn't encrypted, for proving the architecture test still finds one now that every real one is.
 */
final class UnencryptedQueuedNotification extends Notification implements ShouldQueue {}
