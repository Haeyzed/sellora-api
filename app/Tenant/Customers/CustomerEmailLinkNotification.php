<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email with a one-time link into the storefront, to verify a customer's email or to confirm a new one.
 */
final class CustomerEmailLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CustomerEmailTokenPurpose $purpose,
        private readonly string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * Builds the link from the URL template in config/customers.php: {token} is filled in, and {domain} becomes the store domain the request came from.
     */
    public static function forToken(CustomerEmailTokenPurpose $purpose, string $token): self
    {
        $template = config()->string('customers.'.$purpose->key().'.url');

        return new self($purpose, strtr($template, [
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
        $texts = 'customers.'.$this->purpose->key();
        $minutes = $this->purpose->expiresInMinutes();
        $expiry = $this->purpose === CustomerEmailTokenPurpose::Verification
            ? __($texts.'.expiry', ['hours' => intdiv($minutes, 60)])
            : __($texts.'.expiry', ['minutes' => $minutes]);

        return (new MailMessage)
            ->subject(__($texts.'.subject'))
            ->line(__($texts.'.reason'))
            ->action(__($texts.'.action'), $this->url)
            ->line($expiry)
            ->line(__($texts.'.ignore'));
    }

    public function purpose(): CustomerEmailTokenPurpose
    {
        return $this->purpose;
    }

    /**
     * The link the email contains, for tests.
     */
    public function url(): string
    {
        return $this->url;
    }
}
