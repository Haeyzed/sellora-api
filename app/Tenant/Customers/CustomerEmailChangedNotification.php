<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a customer's old email address that the account moved to another, so a takeover doesn't go unnoticed.
 *
 * It never says the new address: whoever reads the old mailbox may not be
 * the customer any more.
 */
final class CustomerEmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $language  The customer's language; the old address isn't the customer, so it doesn't choose one itself.
     */
    public function __construct(?string $language = null)
    {
        $this->afterCommit();

        if ($language !== null) {
            $this->locale($language);
        }
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
            ->subject(__('customers.email_changed.subject'))
            ->line(__('customers.email_changed.reason'))
            ->line(__('customers.email_changed.help'));
    }
}
