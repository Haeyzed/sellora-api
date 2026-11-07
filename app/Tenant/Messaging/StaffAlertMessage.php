<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Tenant\Messaging\Contracts\Message;
use App\Tenant\Messaging\Data\MessageRecipient;
use App\Tenant\Messaging\Models\StaffAlert;
use App\Tenant\Messaging\Services\StaffAlertTexts;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * One staff alert as a message: its title and what happened, written in the staff member's language when it is sent, and a pointer to the alert list.
 */
final readonly class StaffAlertMessage implements Message
{
    public function __construct(public int $staffAlertId) {}

    public function toMail(MessageRecipient $recipient): MailMessage
    {
        $alert = $this->alert();
        $texts = app(StaffAlertTexts::class);

        return (new MailMessage)
            ->subject($texts->title($alert))
            ->line($texts->message($alert))
            ->line(__('staff_alerts.see_dashboard'));
    }

    public function toText(MessageRecipient $recipient): string
    {
        $alert = $this->alert();
        $texts = app(StaffAlertTexts::class);

        return $texts->title($alert).': '.$texts->message($alert);
    }

    private function alert(): StaffAlert
    {
        return StaffAlert::query()->findOrFail($this->staffAlertId);
    }
}
