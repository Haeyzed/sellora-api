<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http;

use App\Shared\Idempotency\ReplayPreparer;
use App\Tenant\Orders\Actions\IssueOrderAccessToken;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAccessToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guest who retries placing their order with the same Idempotency-Key gets a fresh order token in the X-Order-Token header (Step 16): they may never have seen the first response, and without a token they could never reach their order.
 *
 * Replays are scoped to the requester, so this is the guest whose cart placed
 * the order. Tokens are never stored for replay: each is issued fresh and
 * replaces the order's last one. An order on an account gets none; it is
 * reached by signing in.
 */
final readonly class IssueOrderTokenOnReplay implements ReplayPreparer
{
    public function __construct(private IssueOrderAccessToken $issueOrderAccessToken) {}

    /**
     * Only a placed order's answer names an order (data.order.id); a refusal names none, and gets nothing.
     */
    public function prepare(Request $request, Response $replayed): void
    {
        $body = json_decode((string) $replayed->getContent(), true);
        $orderId = is_array($body) && is_array($body['data'] ?? null) && is_array($body['data']['order'] ?? null) ? ($body['data']['order']['id'] ?? null) : null;
        $order = is_string($orderId) ? Order::query()->where('public_id', $orderId)->first() : null;

        if ($order === null || $order->customer_id !== null) {
            return;
        }

        $replayed->headers->set(OrderAccessToken::HEADER, $this->issueOrderAccessToken->handle($order));
    }
}
