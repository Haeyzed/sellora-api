<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Enums\SocialProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email asking an account's owner to confirm connecting a sign-in provider to it (Step 10b). It says plainly that someone is connecting a provider, and to ignore it if that wasn't them.
 */
final class SocialLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SocialProvider $provider,
        private readonly string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * Builds the link from the URL template in config/customers.php: {token} is filled in, and {domain} becomes the store domain the request came from.
     */
    public static function forToken(SocialProvider $provider, string $token): self
    {
        return new self($provider, strtr(config()->string('customers.social_link.url'), [
            '{token}' => rawurlencode($token),
            '{domain}' => request()->getHost(),
        ]));
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
        $provider = ['provider' => $this->providerName()];

        return (new MailMessage)
            ->subject(__('customers.social_link.subject'))
            ->line(__('customers.social_link.reason', $provider))
            ->action(__('customers.social_link.action', $provider), $this->url)
            ->line(__('customers.social_link.expiry', ['minutes' => CustomerEmailTokenPurpose::SocialLink->expiresInMinutes()]))
            ->line(__('customers.social_link.ignore'));
    }

    public function provider(): SocialProvider
    {
        return $this->provider;
    }

    /**
     * The link the email contains, for tests.
     */
    public function url(): string
    {
        return $this->url;
    }

    private function providerName(): string
    {
        return match ($this->provider) {
            SocialProvider::Google => 'Google',
            SocialProvider::Apple => 'Apple',
        };
    }
}
