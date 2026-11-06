<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Jobs;

use App\Tenant\Messaging\Contracts\Message;
use App\Tenant\Messaging\Contracts\MessageAddressee;
use App\Tenant\Messaging\MessageChannels;
use App\Tenant\Messaging\Services\SendingLimit;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one message to one person, through every channel the store may use that can reach them, on the messages queue.
 *
 * Queued only once the change it is about has committed, and encrypted while
 * it waits. Who it is for is looked up when it runs, so it reaches their
 * address and language as they are then. Over the store's sending limit it
 * waits and tries again, never dropped, until messaging.sending.retry_for_hours
 * has passed.
 */
final class SendMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var list<int> Seconds to wait after a channel failed, before each try again. */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly Message $message,
        public readonly MessageAddressee $addressee,
    ) {
        $this->onQueue(config()->string('messaging.queue'));
        $this->afterCommit();
    }

    /**
     * Keeps trying (waiting for its turn, or after a failure) for messaging.sending.retry_for_hours from when it was queued.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(config()->integer('messaging.sending.retry_for_hours'));
    }

    public function handle(MessageChannels $channels, SendingLimit $sendingLimit): void
    {
        $recipient = $this->addressee->recipient();

        if ($recipient === null) {
            return;
        }

        $secondsToWait = $sendingLimit->secondsToWaitBeforeSending();

        if ($secondsToWait > 0) {
            $this->release($secondsToWait);

            return;
        }

        foreach ($channels->usable() as $channel) {
            if ($channel->canReach($recipient)) {
                $channel->send($this->message, $recipient);
            }
        }
    }
}
