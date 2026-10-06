<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email telling a new store's owner their store is ready, with a link to sign in to its dashboard.
 */
final class StoreReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $storeName,
        private readonly string $dashboardUrl,
    ) {
        $this->afterCommit();
    }

    /**
     * Builds the dashboard link from the URL template in config/platform.php: {domain} becomes the store's platform domain.
     */
    public static function forStore(Tenant $tenant, string $domain): self
    {
        return new self($tenant->name, strtr(config()->string('platform.store_dashboard_url'), ['{domain}' => $domain]));
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
            ->subject(__('store_registration.ready.subject', ['store' => $this->storeName]))
            ->line(__('store_registration.ready.line', ['store' => $this->storeName]))
            ->action(__('store_registration.ready.action'), $this->dashboardUrl);
    }

    /**
     * The link the email contains, for tests.
     */
    public function dashboardUrl(): string
    {
        return $this->dashboardUrl;
    }
}
