<?php

declare(strict_types=1);

namespace App\Tenant\Returns;

use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Tenant\Returns\Models\ReturnSettings;
use Carbon\CarbonImmutable;

/**
 * A new store's return settings, from the defaults in config/returns.php: created when the store is set up, so a store always has them (section 10).
 *
 * The row is inserted only if missing, so setting a store up again never
 * overwrites settings staff have changed. Reads never create it.
 */
final readonly class ReturnSettingsDefaults implements StoreSettingsSetup
{
    public function initialize(): void
    {
        $now = CarbonImmutable::now();

        ReturnSettings::query()->insertOrIgnore(self::row($now));
    }

    /**
     * The settings row a store starts with.
     *
     * @return array{id: int, window_days: int, customer_requests: bool, photos_required: bool, created_at: CarbonImmutable, updated_at: CarbonImmutable}
     */
    public static function row(CarbonImmutable $now): array
    {
        return [
            'id' => 1,
            'window_days' => config()->integer('returns.window_days'),
            'customer_requests' => config()->boolean('returns.customer_requests'),
            'photos_required' => config()->boolean('returns.photos_required'),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
