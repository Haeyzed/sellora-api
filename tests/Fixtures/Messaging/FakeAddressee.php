<?php

declare(strict_types=1);

namespace Tests\Fixtures\Messaging;

use App\Tenant\Messaging\Contracts\MessageAddressee;
use App\Tenant\Messaging\Data\MessageRecipient;

/**
 * Someone for the channel tests to reach, as given; null for someone who can no longer receive messages.
 */
final readonly class FakeAddressee implements MessageAddressee
{
    public function __construct(private ?MessageRecipient $recipient) {}

    public function recipient(): ?MessageRecipient
    {
        return $this->recipient;
    }
}
