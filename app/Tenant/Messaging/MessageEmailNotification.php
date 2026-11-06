<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Tenant\Messaging\Contracts\Message;
use App\Tenant\Messaging\Data\MessageRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A store message as one email, written in the recipient's language. Sent at once by EmailChannel, from a queued SendMessage, so never queued itself.
 */
final class MessageEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Message $message,
        private readonly MessageRecipient $recipient,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->message->toMail($this->recipient);
    }

    /**
     * The message the email carries, for tests.
     */
    public function message(): Message
    {
        return $this->message;
    }
}
