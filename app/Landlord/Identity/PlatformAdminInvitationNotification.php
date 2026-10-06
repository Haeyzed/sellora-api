<?php

declare(strict_types=1);

namespace App\Landlord\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email inviting someone to join Sellora's team, with a one-time link into the platform admin app.
 */
final class PlatformAdminInvitationNotification extends Notification implements ShouldQueue
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
     * Builds the link from the URL template in config/auth.php, filling in {token}.
     */
    public static function forToken(string $token, string $inviterName): self
    {
        $acceptUrl = strtr(config()->string('auth.platform_admin_invitations.accept_url'), ['{token}' => rawurlencode($token)]);

        return new self($acceptUrl, $inviterName, config()->integer('auth.platform_admin_invitations.expire_days'));
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
            ->subject(__('invitations.platform_admin.subject', ['inviter' => $this->inviterName]))
            ->line(__('invitations.platform_admin.reason', ['inviter' => $this->inviterName]))
            ->action(__('invitations.platform_admin.action'), $this->acceptUrl)
            ->line(__('invitations.platform_admin.expiry', ['days' => $this->expiresInDays]))
            ->line(__('invitations.platform_admin.two_factor'))
            ->line(__('invitations.platform_admin.ignore'));
    }

    /**
     * The link the email contains, for tests.
     */
    public function acceptUrl(): string
    {
        return $this->acceptUrl;
    }
}
