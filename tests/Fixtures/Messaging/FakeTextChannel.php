<?php

declare(strict_types=1);

namespace Tests\Fixtures\Messaging;

use App\Tenant\Messaging\Contracts\Message;
use App\Tenant\Messaging\Contracts\MessageChannel;
use App\Tenant\Messaging\Data\MessageRecipient;
use Illuminate\Support\Traits\Localizable;

/**
 * An integration's text channel (as an SMS provider would add), for proving a channel is used only while its integration is Enabled. Remembers what it sent.
 */
final class FakeTextChannel implements MessageChannel
{
    use Localizable;

    public const string KEY = 'fake_sms';

    /** The integration's feature key. */
    public const string FEATURE = 'fake_sms';

    /** @var list<array{phone: string, text: string}> */
    public static array $sent = [];

    public function key(): string
    {
        return self::KEY;
    }

    public function canReach(MessageRecipient $recipient): bool
    {
        return $recipient->phone !== null;
    }

    public function send(Message $message, MessageRecipient $recipient): void
    {
        self::$sent[] = ['phone' => (string) $recipient->phone, 'text' => $this->withLocale($recipient->locale, static fn (): string => $message->toText($recipient))];
    }
}
