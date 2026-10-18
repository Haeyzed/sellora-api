<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use Carbon\CarbonImmutable;

/**
 * Empties what payment gateways sent in webhooks once the retention period has passed, in every store: it may name buyers. The event itself is kept a while longer (PaymentWebhookEventRetention), so a late resend is still recognised.
 */
final class PaymentWebhookPayloadRetention implements RetentionPolicy
{
    private const int BATCH_SIZE = 1000;

    public function periodKey(): string
    {
        return 'payment_webhook_payloads';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $emptied = 0;

        do {
            $ids = PaymentWebhookEvent::query()->whereNotNull('payload')->where('received_at', '<', $cutoff)->orderBy('id')->limit(self::BATCH_SIZE)->pluck('id');
            $inBatch = $ids->isEmpty() ? 0 : PaymentWebhookEvent::query()->whereIn('id', $ids)->update(['payload' => null]);
            $emptied += $inBatch;
        } while ($inBatch === self::BATCH_SIZE);

        return $emptied;
    }
}
