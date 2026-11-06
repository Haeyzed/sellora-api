<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Listeners;

use App\Shared\Tenancy\Contracts\StoreMailIdentity;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Sends every email a store sends in the store's name, from the platform's address, with replies going to the store: its alerts and messages as well as account emails such as invitations and verification links.
 *
 * Only while a store is the current one; the platform's own emails keep the
 * platform's name. The address stays the platform's, because the platform's
 * mail provider can only send from a domain it has verified (a store's own
 * domain comes with custom domains). A reply-to the email already has is kept.
 */
final readonly class SendStoreMailInTheStoreNameWhenMessageSending
{
    public function __construct(private StoreMailIdentity $storeMailIdentity) {}

    public function handle(MessageSending $event): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $email = $event->message;
        $email->from(new Address(config()->string('mail.from.address'), $this->storeMailIdentity->senderName()));
        $replyTo = $this->storeMailIdentity->replyToAddress();

        if ($email->getReplyTo() === [] && $replyTo !== null) {
            $email->replyTo($replyTo);
        }
    }
}
