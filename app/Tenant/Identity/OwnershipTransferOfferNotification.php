<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email telling a staff member that the owner wants to hand them the store, with a link into the store dashboard to accept it while signed in.
 */
final class OwnershipTransferOfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $acceptUrl,
        private readonly string $ownerName,
        private readonly int $expiresInHours,
    ) {
        $this->afterCommit();
    }

    /**
     * Builds the link from the URL template in config/auth.php: {transfer} is filled in, and {domain} becomes the store domain the request came from.
     */
    public static function forTransfer(string $transferId, string $ownerName): self
    {
        $acceptUrl = strtr(config()->string('auth.ownership_transfers.accept_url'), [
            '{transfer}' => rawurlencode($transferId),
            '{domain}' => request()->getHost(),
        ]);

        return new self($acceptUrl, $ownerName, config()->integer('auth.ownership_transfers.expire_hours'));
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
            ->subject(__('ownership_transfers.offer.subject', ['owner' => $this->ownerName]))
            ->line(__('ownership_transfers.offer.reason', ['owner' => $this->ownerName]))
            ->line(__('ownership_transfers.offer.terms'))
            ->action(__('ownership_transfers.offer.action'), $this->acceptUrl)
            ->line(__('ownership_transfers.offer.expiry', ['hours' => $this->expiresInHours]))
            ->line(__('ownership_transfers.offer.ignore'));
    }

    /**
     * The link the email contains, for tests.
     */
    public function acceptUrl(): string
    {
        return $this->acceptUrl;
    }
}
