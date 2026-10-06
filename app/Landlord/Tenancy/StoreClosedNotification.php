<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The emails to a closed store's owner: when it closes, and again shortly before its data is deleted for good.
 *
 * A closed store can't be signed in to, so the owner can no longer export its
 * data themselves; until the purge date, Sellora support can restore it.
 */
final class StoreClosedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $storeName,
        private readonly CarbonImmutable $purgeAfter,
        public readonly bool $isReminder,
    ) {
        $this->afterCommit();
    }

    public static function forStore(Tenant $store): self
    {
        return new self($store->name, $store->purge_after ?? CarbonImmutable::now(), isReminder: false);
    }

    public static function reminderForStore(Tenant $store): self
    {
        return new self($store->name, $store->purge_after ?? CarbonImmutable::now(), isReminder: true);
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
        $replacements = ['store' => $this->storeName, 'date' => $this->purgeAfter->toFormattedDayDateString()];
        $kind = $this->isReminder ? 'reminder' : 'closed';

        return (new MailMessage)
            ->subject(__("store_closure.{$kind}.subject", $replacements))
            ->line(__("store_closure.{$kind}.line", $replacements))
            ->line(__('store_closure.export', $replacements))
            ->line(__('store_closure.not_you'));
    }

    /**
     * When the store's data will be deleted, for tests.
     */
    public function purgeAfter(): CarbonImmutable
    {
        return $this->purgeAfter;
    }
}
