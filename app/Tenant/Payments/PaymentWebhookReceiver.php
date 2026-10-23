<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Integrations\Contracts\PaymentWebhooks;
use App\Shared\Integrations\WebhookReceipt;
use App\Tenant\Payments\Contracts\PaymentGateway;
use App\Tenant\Payments\Data\GatewayWebhookEvent;
use App\Tenant\Payments\Data\IncomingWebhook;
use App\Tenant\Payments\Jobs\ProcessPaymentWebhook;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;
use SensitiveParameter;

/**
 * The store's side of a payment webhook, in this order: the store's webhook limit and the endpoint's own, the connection the endpoint belongs to and the mode it serves, then the signature, checked with that connection's keys before anything in the webhook is parsed, stored or acted on.
 *
 * Never gated by the plan or the store's status: webhooks are money already
 * in motion, so a gateway disconnected while money was in motion still
 * receives them, and keys replaced meanwhile still check them. A webhook to
 * the endpoint of the mode the connection is not in is refused unread: its
 * keys are not the ones held. One that checks out is recorded once per
 * gateway event ID (a resend is accepted and ignored), with what it said
 * encrypted, and is handled on the payments queue.
 */
final readonly class PaymentWebhookReceiver implements PaymentWebhooks
{
    /** A longer event ID or reference than a column holds is replaced by its hash, or dropped: ours are short. */
    private const int MAX_EVENT_ID_LENGTH = 255;

    private const int MAX_REFERENCE_LENGTH = 64;

    public function __construct(private PaymentGatewayRegistry $registry) {}

    public function receive(string $endpointId, #[SensitiveParameter] string $body, array $headers): WebhookReceipt
    {
        if (! $this->withinLimit('tenant-webhooks') || ! $this->withinLimit('tenant-webhook-endpoints', $endpointId)) {
            return WebhookReceipt::Throttled;
        }

        // Whatever its status: only a connection still holding keys has endpoints (payment_gateway_connections_keys_lead_webhooks).
        $connection = PaymentGatewayConnection::query()
            ->where(static fn (Builder $endpoints) => $endpoints->where('test_endpoint_id', $endpointId)->orWhere('live_endpoint_id', $endpointId))
            ->first();

        if ($connection === null || $connection->modeOfEndpoint($endpointId) !== $connection->mode || ! $this->registry->has($connection->gateway)) {
            return WebhookReceipt::UnknownEndpoint;
        }

        $gateway = $this->registry->get($connection->gateway);
        $webhook = new IncomingWebhook($body, $headers);

        if (! $this->isSignedForConnection($gateway, $connection, $webhook)) {
            // Nothing from the webhook itself: it is untrusted.
            Log::notice('A payment webhook was refused: its signature did not match the store\'s keys.', ['gateway' => $connection->gateway]);

            return WebhookReceipt::SignatureInvalid;
        }

        $this->record($connection, $gateway, $webhook);

        return WebhookReceipt::Accepted;
    }

    private function isSignedForConnection(PaymentGateway $gateway, PaymentGatewayConnection $connection, IncomingWebhook $webhook): bool
    {
        foreach ($connection->webhookCredentials() as $credentials) {
            if ($gateway->verifyWebhookSignature($credentials, $webhook)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Counts the webhook against a store-wide limit, or one per endpoint, so one endpoint flooded never takes the store's whole allowance.
     */
    private function withinLimit(string $name, ?string $endpointId = null): bool
    {
        $limiter = RateLimiter::limiter($name);
        $limit = $limiter === null ? null : $limiter($endpointId);

        if (! $limit instanceof Limit) {
            throw new LogicException("The {$name} rate limit must be one limit (RateLimitServiceProvider).");
        }

        return RateLimiter::attempt($limit->key, $limit->maxAttempts, static fn (): bool => true, $limit->decaySeconds) !== false;
    }

    /**
     * Records the webhook unless its event was already recorded, and queues handling it.
     */
    private function record(PaymentGatewayConnection $connection, PaymentGateway $gateway, IncomingWebhook $webhook): void
    {
        $event = $gateway->parseWebhook($webhook);
        $eventId = $this->eventIdOf($event, $webhook);

        $record = new PaymentWebhookEvent;
        $record->forceFill([
            'connection_id' => $connection->id,
            'gateway' => $connection->gateway,
            'event_id' => $eventId,
            'kind' => $event->kind,
            'reference' => $event->reference !== null && mb_strlen($event->reference) <= self::MAX_REFERENCE_LENGTH ? $event->reference : null,
            'payload' => $webhook->body,
            'received_at' => CarbonImmutable::now(),
        ]);

        // A resend, or the same event arriving twice at once, is left to the unique index.
        $isNew = PaymentWebhookEvent::query()->insertOrIgnore($record->getAttributes()) === 1;

        if ($isNew) {
            $recorded = PaymentWebhookEvent::query()->where('gateway', $connection->gateway)->where('event_id', $eventId)->sole();
            ProcessPaymentWebhook::dispatch($recorded->id);
        }
    }

    /**
     * The gateway's event ID; a webhook without one is known by its body, so the same body is still handled once.
     */
    private function eventIdOf(GatewayWebhookEvent $event, IncomingWebhook $webhook): string
    {
        if ($event->eventId === '') {
            return 'body:'.hash('sha256', $webhook->body);
        }

        return mb_strlen($event->eventId) <= self::MAX_EVENT_ID_LENGTH ? $event->eventId : 'sha256:'.hash('sha256', $event->eventId);
    }
}
