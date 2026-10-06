<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

/**
 * Builds keys (for rate limits and similar counters) that always include the current store, so stores never share a counter.
 *
 * Laravel's rate limiter keeps the cache it was created with, which is not
 * separated per store. Without the store in the key, staff member #12 in one
 * store and staff member #12 in another would share one login limit.
 * Platform (central) requests get their own "central" prefix.
 */
final class TenantScopedKey
{
    /**
     * Joins the given parts into one key, prefixed with the current store or "central".
     */
    public static function make(string ...$parts): string
    {
        $tenant = tenant();
        $prefix = $tenant === null ? 'central' : 'tenant:'.$tenant->getTenantKey();

        return implode('|', [$prefix, ...$parts]);
    }

    /**
     * A key for a personal identifier (email, phone) that never stores the identifier itself in the cache.
     */
    public static function forIdentifier(string $purpose, string $identifier): string
    {
        return self::make($purpose, hash('sha256', $identifier));
    }
}
