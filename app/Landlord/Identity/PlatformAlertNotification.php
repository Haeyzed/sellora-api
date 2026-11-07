<?php

declare(strict_types=1);

namespace App\Landlord\Identity;

use App\Landlord\Identity\Enums\PlatformAlertKind;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email telling a platform admin that something on the platform needs Sellora's team, written in their saved language.
 */
final class PlatformAlertNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|int>  $details  The text's placeholders.
     */
    public function __construct(
        public readonly PlatformAlertKind $kind,
        public readonly array $details,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('platform_alerts.'.$this->kind->value.'.title', $this->details))
            ->line(__('platform_alerts.'.$this->kind->value.'.message', $this->details))
            ->line(__('platform_alerts.see_dashboard', ['app' => config('app.name')]));
    }
}
