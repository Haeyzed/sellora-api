<?php

declare(strict_types=1);

namespace App\Tenant\Cart;

/**
 * The secret a guest's cart is reached with: 32 random bytes, shown once when the cart is made, stored only as its hash (section 14).
 *
 * Storefront apps send it back in the X-Cart-Token header, never in a URL,
 * so it stays out of server and proxy logs. Each store has its own database,
 * so a token only ever works in the store that issued it.
 */
final class CartToken
{
    public const string HEADER = 'X-Cart-Token';

    /** 32 bytes in unpadded base64url. */
    public const string FORMAT = '/^[A-Za-z0-9_-]{43}$/';

    private const int BYTES = 32;

    /**
     * A new token, to be shown to the guest once.
     */
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::BYTES)), '+/', '-_'), '=');
    }

    /**
     * What is stored and looked up instead of the token: its SHA-256, in hex.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Whether a value could be a token at all, so nothing malformed reaches a query.
     */
    public static function isWellFormed(string $token): bool
    {
        return preg_match(self::FORMAT, $token) === 1;
    }
}
