<?php

declare(strict_types=1);

namespace App\Landlord\Integrations\Http\Controllers;

use App\Landlord\Integrations\Actions\ReceivePaymentWebhook;
use App\Landlord\Integrations\Exceptions\WebhookSignatureInvalidException;
use App\Shared\Http\Controller;
use App\Shared\Integrations\WebhookReceipt;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentWebhookController extends Controller
{
    /**
     * Receive a payment gateway's webhook for a store.
     *
     * Public, on the central domain: the gateway signs it with the store's own
     * keys, and the store checks the signature before reading anything in it.
     * Each store's webhooks are limited per minute. Endpoint IDs that lead
     * nowhere are counted per IP address and slowed down; only those, so a
     * gateway resending to a store that disconnected never slows the webhooks
     * it sends to every other store from the same address.
     *
     * @unauthenticated
     *
     * @throws WebhookSignatureInvalidException
     */
    public function __invoke(Request $request, string $endpoint, ReceivePaymentWebhook $receivePaymentWebhook): JsonResponse
    {
        $receipt = $receivePaymentWebhook->handle($endpoint, $request->getContent(), $this->headersOf($request));

        return match ($receipt) {
            WebhookReceipt::Accepted => new JsonResponse(['received' => true]),
            WebhookReceipt::SignatureInvalid => throw new WebhookSignatureInvalidException,
            WebhookReceipt::Throttled => throw new ThrottleRequestsException,
            WebhookReceipt::UnknownEndpoint, null => $this->unknownEndpoint($request),
        };
    }

    /**
     * @throws ThrottleRequestsException When this address has sent too many to endpoints that lead nowhere.
     */
    private function unknownEndpoint(Request $request): never
    {
        $limit = $this->unknownEndpointLimit($request);

        if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
            throw new ThrottleRequestsException;
        }

        RateLimiter::hit($limit->key, $limit->decaySeconds);

        throw new NotFoundHttpException;
    }

    private function unknownEndpointLimit(Request $request): Limit
    {
        $limiter = RateLimiter::limiter('unknown-webhook-endpoints');
        $limit = $limiter === null ? null : $limiter($request);

        return $limit instanceof Limit ? $limit : throw new LogicException('The unknown-webhook-endpoints rate limit must be one limit (RateLimitServiceProvider).');
    }

    /**
     * @return array<string, string>
     */
    private function headersOf(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->keys() as $name) {
            $headers[mb_strtolower($name)] = (string) $request->headers->get($name);
        }

        return $headers;
    }
}
