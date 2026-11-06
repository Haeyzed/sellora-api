<?php

declare(strict_types=1);

namespace Tests\Fixtures\Messaging;

use App\Tenant\Messaging\Contracts\Message;
use App\Tenant\Messaging\Data\MessageRecipient;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A message for the channel tests: its subject is in the language it is written in, so a test can tell which one was used.
 */
final readonly class FakeMessage implements Message
{
    public function __construct(public string $reference = 'test') {}

    public function toMail(MessageRecipient $recipient): MailMessage
    {
        return (new MailMessage)->subject($this->subject())->line($this->reference);
    }

    public function toText(MessageRecipient $recipient): string
    {
        return $this->subject().': '.$this->reference;
    }

    /**
     * "Bonjour" while it is written in French, "Hello" otherwise.
     */
    public function subject(): string
    {
        return app()->getLocale() === 'fr' ? 'Bonjour' : 'Hello';
    }
}
