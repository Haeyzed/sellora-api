<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email inviting someone to join a store's team, with a one-time link into the store dashboard.
 */
final class StaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $acceptUrl,
        private readonly string $inviterName,
        private readonly int $expiresInDays,
    ) {
        $this->afterCommit();
    }

    /**
     * Builds the link from the URL template in config/auth.php: {token} is filled in, and {domain} becomes the store domain the request came from.
     */
    public static function forToken(string $token, string $inviterName): self
    {
        $acceptUrl = strtr(config()->string('auth.staff_invitations.accept_url'), [
            '{token}' => rawurlencode($token),
            '{domain}' => request()->getHost(),
        ]);

        return new self($acceptUrl, $inviterName, config()->integer('auth.staff_invitations.expire_days'));
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
            ->subject(__('invitations.staff.subject', ['inviter' => $this->inviterName]))
            ->line(__('invitations.staff.reason', ['inviter' => $this->inviterName]))
            ->action(__('invitations.staff.action'), $this->acceptUrl)
            ->line(__('invitations.staff.expiry', ['days' => $this->expiresInDays]))
            ->line(__('invitations.staff.ignore'));
    }

    /**
     * The link the email contains, for tests.
     */
    public function acceptUrl(): string
    {
        return $this->acceptUrl;
    }
}
