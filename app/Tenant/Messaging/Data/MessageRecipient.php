<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Data;

/**
 * Where and in which language a message reaches someone, worked out just before it is sent. Never queued.
 */
final readonly class MessageRecipient
{
    /**
     * @param  string  $locale  The language the message is written in, such as "fr".
     * @param  string|null  $email  Null when they have no email address.
     * @param  string|null  $name  How the email greets and addresses them.
     * @param  string|null  $phone  E.164, for text channels; null when they have none.
     */
    public function __construct(
        public string $locale,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $phone = null,
    ) {}
}
