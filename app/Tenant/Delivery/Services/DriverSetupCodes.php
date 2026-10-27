<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Models\DriverSetupCode;
use Carbon\CarbonImmutable;

/**
 * The 8-digit one-time codes a driver uses to choose their own PIN.
 *
 * Only a keyed hash of a code is stored. A plain hash of an 8-digit number
 * could be reversed by trying all hundred million, so the app key is mixed in.
 */
final readonly class DriverSetupCodes
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config()->string('app.key'));
    }

    public function matches(string $code, string $hash): bool
    {
        return hash_equals($hash, $this->hash($code));
    }

    /**
     * Stops the driver's live code working, if they have one: a newer code, deactivation, a phone change or erasure replaces it.
     */
    public function voidLiveCodeOf(Driver $driver): void
    {
        DriverSetupCode::query()->where('driver_id', $driver->id)->live()->update([
            'voided_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
    }
}
