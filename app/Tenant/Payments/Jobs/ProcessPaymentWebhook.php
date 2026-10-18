<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Jobs;

use App\Tenant\Payments\Actions\HandlePaymentWebhook;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Handles a recorded payment webhook on the payments queue, in the store it came to. Safe to run twice: a handled event is left alone, and asking the gateway again changes nothing.
 */
final class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $webhookEventId)
    {
        $this->onQueue(config()->string('payments.queue'));
    }

    public function handle(HandlePaymentWebhook $handlePaymentWebhook): void
    {
        $event = PaymentWebhookEvent::query()->find($this->webhookEventId);

        if ($event !== null) {
            $handlePaymentWebhook->handle($event);
        }
    }
}
