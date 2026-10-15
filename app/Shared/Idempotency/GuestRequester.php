<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use Illuminate\Http\Request;

/**
 * Tells who an anonymous request comes from, such as a guest's cart, so their Idempotency-Keys are theirs alone (section 11).
 *
 * An IP address never identifies a requester: many shoppers can share one.
 */
interface GuestRequester
{
    /**
     * A stable scope for the guest sending the request, holding no secret in readable form; null when the request names no guest.
     */
    public function scopeOf(Request $request): ?string;
}
