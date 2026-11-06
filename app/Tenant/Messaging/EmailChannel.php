<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Tenant\Messaging\Contracts\Message;
use App\Tenant\Messaging\Contracts\MessageChannel;
use App\Tenant\Messaging\Data\MessageRecipient;
use Illuminate\Support\Facades\Notification;

/**
 * Core's own channel: email, through the platform's mail provider, from the store's name (MessagingSettings).
 */
final readonly class EmailChannel implements MessageChannel
{
    public const string KEY = 'email';

    public function key(): string
    {
        return self::KEY;
    }

    public function canReach(MessageRecipient $recipient): bool
    {
        return $recipient->email !== null && $recipient->email !== '';
    }

    /**
     * Sends at once: this already runs from the queue (SendMessage), so it is never queued a second time.
     */
    public function send(Message $message, MessageRecipient $recipient): void
    {
        $address = (string) $recipient->email;

        Notification::route('mail', $recipient->name === null ? $address : [$address => $recipient->name])
            ->notifyNow((new MessageEmailNotification($message, $recipient))->locale($recipient->locale));
    }
}
