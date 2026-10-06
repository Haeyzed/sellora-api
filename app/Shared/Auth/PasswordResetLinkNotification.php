<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email with a link to choose a new password, sent to any kind of account that asked for one.
 *
 * The link points into the right frontend app, built from the URL template
 * of the account's password broker in config/auth.php.
 */
final class PasswordResetLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $resetUrl,
        private readonly int $expiresInMinutes,
    ) {
        $this->afterCommit();
    }

    /**
     * Builds the reset link from a broker's URL template: {token} and {email} are filled in, and {domain} becomes the store domain the request came from.
     */
    public static function forBroker(string $broker, string $token, string $email): self
    {
        $template = config()->string("auth.passwords.{$broker}.reset_url");
        $resetUrl = strtr($template, [
            '{token}' => rawurlencode($token),
            '{email}' => rawurlencode($email),
            '{domain}' => request()->getHost(),
        ]);

        return new self($resetUrl, config()->integer("auth.passwords.{$broker}.expire"));
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
            ->subject(__('passwords.notification.subject', ['app' => config('app.name')]))
            ->line(__('passwords.notification.reason'))
            ->action(__('passwords.notification.action'), $this->resetUrl)
            ->line(__('passwords.notification.expiry', ['minutes' => $this->expiresInMinutes]))
            ->line(__('passwords.notification.ignore'));
    }

    /**
     * The link the email contains, for tests and logs that must never include the token itself elsewhere.
     */
    public function resetUrl(): string
    {
        return $this->resetUrl;
    }
}
