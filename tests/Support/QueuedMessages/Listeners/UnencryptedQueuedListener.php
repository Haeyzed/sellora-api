<?php

declare(strict_types=1);

namespace Tests\Support\QueuedMessages\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A queued listener that isn't encrypted, for proving the architecture test finds one before the code has any.
 */
final class UnencryptedQueuedListener implements ShouldQueue
{
    public function handle(object $event): void {}
}
