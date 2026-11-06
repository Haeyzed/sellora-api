<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Shared\Tenancy\Contracts\StoreMailIdentity;
use App\Tenant\Messaging\Models\MessagingSettings;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * The store's emails come from the sender name in its messaging settings (or else the store's name), and replies go to the reply-to address there (or else the store's contact email).
 */
final readonly class MessagingMailIdentity implements StoreMailIdentity
{
    public function __construct(private FindStoreSettings $findStoreSettings) {}

    public function senderName(): string
    {
        return MessagingSettings::current()->sender_name ?? $this->findStoreSettings->handle()->name;
    }

    public function replyToAddress(): ?string
    {
        return MessagingSettings::current()->reply_to_email ?? $this->findStoreSettings->handle()->contact_email;
    }
}
