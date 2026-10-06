<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * The email with the 6-digit code that confirms a merchant owns the email they are registering a store with.
 */
final class StoreRegistrationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[SensitiveParameter] private readonly string $code,
        private readonly string $storeName,
        private readonly int $expiresInMinutes,
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
            ->subject(__('store_registration.code.subject', ['code' => $this->code]))
            ->line(__('store_registration.code.reason', ['store' => $this->storeName]))
            ->line(__('store_registration.code.code', ['code' => $this->code]))
            ->line(__('store_registration.code.expiry', ['minutes' => $this->expiresInMinutes]))
            ->line(__('store_registration.code.ignore'));
    }

    /**
     * The code the email contains, for tests.
     */
    public function code(): string
    {
        return $this->code;
    }
}
