<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Data;

/**
 * What staff change in the messaging settings. Each setting is changed only when its flag says it was sent, so null can mean "back to the store's own".
 */
final readonly class MessagingSettingsChanges
{
    public function __construct(
        public bool $changesSenderName = false,
        public ?string $senderName = null,
        public bool $changesReplyToEmail = false,
        public ?string $replyToEmail = null,
        public ?bool $hasNewOrderAlertEmails = null,
    ) {}
}
