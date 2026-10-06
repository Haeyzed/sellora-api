<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email both people get once an ownership transfer is accepted: the new owner and the previous one.
 */
final class OwnershipTransferredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $toNewOwner  Whether this copy goes to the new owner (otherwise to the previous one).
     */
    public function __construct(
        private readonly string $storeName,
        private readonly string $newOwnerName,
        public readonly bool $toNewOwner,
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
        $lines = $this->toNewOwner ? 'ownership_transfers.completed.new_owner' : 'ownership_transfers.completed.previous_owner';

        return (new MailMessage)
            ->subject(__('ownership_transfers.completed.subject', ['store' => $this->storeName]))
            ->line(__($lines, ['store' => $this->storeName, 'owner' => $this->newOwnerName]))
            ->line(__('ownership_transfers.completed.not_you'));
    }
}
