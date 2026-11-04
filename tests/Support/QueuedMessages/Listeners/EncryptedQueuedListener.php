<?php

declare(strict_types=1);

namespace Tests\Support\QueuedMessages\Listeners;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A queued listener that is encrypted, which the architecture test must accept.
 */
final class EncryptedQueuedListener implements ShouldBeEncrypted, ShouldQueue
{
    public function handle(object $event): void {}
}
