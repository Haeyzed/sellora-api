<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Contracts;

use App\Tenant\Messaging\Data\MessageRecipient;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Something a store tells someone: an alert for staff, or (from Step 20b) a message about a customer's order.
 *
 * A message holds only what identifies it (IDs, never names, addresses or
 * other personal data), because it waits in the queue. It is written when it
 * is sent, in the recipient's language, by whichever channel sends it: email
 * through toMail(), and text channels (SMS, WhatsApp) through toText().
 */
interface Message
{
    /**
     * The email: subject, lines and any button, in the current language (the recipient's, set by the channel).
     */
    public function toMail(MessageRecipient $recipient): MailMessage;

    /**
     * The same message as short plain text, for channels that send only text, in the current language.
     */
    public function toText(MessageRecipient $recipient): string;
}
