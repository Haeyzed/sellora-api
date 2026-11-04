<?php

declare(strict_types=1);

namespace Tests\Support\QueuedMessages;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/**
 * A queued mailable that isn't encrypted, for proving the architecture test finds one before the code has any.
 */
final class UnencryptedQueuedMailable extends Mailable implements ShouldQueue {}
