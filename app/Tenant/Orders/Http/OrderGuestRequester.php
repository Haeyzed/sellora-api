<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http;

use App\Shared\Idempotency\GuestRequester;
use App\Tenant\Orders\Models\OrderAccessToken;
use Illuminate\Http\Request;

/**
 * A guest is known by their order's access token: their Idempotency-Keys are scoped to its hash, never to the token itself.
 */
final readonly class OrderGuestRequester implements GuestRequester
{
    public function scopeOf(Request $request): ?string
    {
        $token = $request->headers->get(OrderAccessToken::HEADER);

        return is_string($token) && OrderAccessToken::isWellFormed($token) ? 'order:'.OrderAccessToken::hash($token) : null;
    }
}
