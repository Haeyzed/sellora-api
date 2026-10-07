<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email telling whoever asked for a store export that it is ready, with a link to where they download it while signed in.
 *
 * Sent to platform admins and store owners alike. The link opens the
 * dashboard, never the file: the file can only be downloaded by the
 * requester while signed in.
 */
final class StoreExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $storeName,
        private readonly CarbonImmutable $expiresAt,
        private readonly string $exportUrl,
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
        $date = $this->expiresAt->toDayDateTimeString().' UTC';

        return (new MailMessage)
            ->subject(__('store_exports.ready.subject', ['store' => $this->storeName]))
            ->line(__('store_exports.ready.line', ['store' => $this->storeName, 'date' => $date]))
            ->action(__('store_exports.ready.action'), $this->exportUrl)
            ->line(__('store_exports.ready.signed_in'));
    }

    /**
     * The link the email contains, for tests.
     */
    public function exportUrl(): string
    {
        return $this->exportUrl;
    }
}
