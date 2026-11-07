<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Tenant\Messaging\Contracts\Message;
use App\Tenant\Messaging\Data\MessageRecipient;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent once an hour to a staff member when alerts for them were held back by the hourly email cap: the alerts are waiting in their alert list.
 */
final readonly class StaffAlertsWaitingMessage implements Message
{
    public function toMail(MessageRecipient $recipient): MailMessage
    {
        return (new MailMessage)
            ->subject(__('staff_alerts.waiting.title'))
            ->line(__('staff_alerts.waiting.message'))
            ->line(__('staff_alerts.see_dashboard'));
    }

    public function toText(MessageRecipient $recipient): string
    {
        return __('staff_alerts.waiting.title').': '.__('staff_alerts.waiting.message');
    }
}
