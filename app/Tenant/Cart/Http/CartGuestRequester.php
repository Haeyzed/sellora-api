<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http;

use App\Shared\Idempotency\GuestRequester;
use App\Tenant\Cart\CartToken;
use Illuminate\Http\Request;

/**
 * A guest is known by their cart token: their Idempotency-Keys are scoped to its hash, never to the token itself.
 */
final readonly class CartGuestRequester implements GuestRequester
{
    public function scopeOf(Request $request): ?string
    {
        $token = $request->headers->get(CartToken::HEADER);

        return is_string($token) && CartToken::isWellFormed($token) ? 'cart:'.CartToken::hash($token) : null;
    }
}
