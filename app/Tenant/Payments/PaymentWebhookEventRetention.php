<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes recorded payment webhooks once gateways have long stopped resending them, in every store. What they did is in the payments' own history.
 *
 * @extends TimestampRetentionPolicy<PaymentWebhookEvent>
 */
final class PaymentWebhookEventRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'payment_webhook_events';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<PaymentWebhookEvent>
     */
    protected function query(): Builder
    {
        return PaymentWebhookEvent::query();
    }

    protected function timestampColumn(): string
    {
        return 'received_at';
    }
}
