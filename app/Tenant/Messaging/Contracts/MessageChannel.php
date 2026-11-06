<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Contracts;

use App\Tenant\Messaging\Data\MessageRecipient;

/**
 * One way of reaching people: core's email, or a channel an integration adds (SMS, WhatsApp, push).
 *
 * Integrations register their channel in MessageChannels from their service
 * provider, under their feature key, and it is used only while their
 * integration is Enabled for the store. Core never names a channel's class.
 */
interface MessageChannel
{
    /**
     * The channel's key, such as "email" or "whatsapp": lowercase letters, digits and underscores.
     */
    public function key(): string;

    /**
     * Whether this channel has a way to reach the recipient (an email address for email, a phone number for SMS).
     */
    public function canReach(MessageRecipient $recipient): bool;

    /**
     * Sends the message now, in the recipient's language. Called from the queue, never inside a database transaction.
     */
    public function send(Message $message, MessageRecipient $recipient): void;
}
