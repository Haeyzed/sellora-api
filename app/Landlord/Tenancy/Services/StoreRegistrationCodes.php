<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

/**
 * The 6-digit codes that prove a merchant owns the email they signed up with.
 *
 * Only a keyed hash of a code is stored. A plain hash of a 6-digit number
 * could be reversed by trying all million, so the app key is mixed in.
 */
final readonly class StoreRegistrationCodes
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config()->string('app.key'));
    }

    public function matches(string $code, string $hash): bool
    {
        return hash_equals($hash, $this->hash($code));
    }
}
