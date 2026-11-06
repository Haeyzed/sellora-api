<?php

declare(strict_types=1);

namespace App\Tenant\Messaging;

use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Tenant\Messaging\Models\MessagingSettings;
use Carbon\CarbonImmutable;

/**
 * Gives a new store its messaging settings row when it is set up: emails from the store's own name, replies to its contact email, and new-order alerts emailed. Running it again changes nothing.
 */
final readonly class MessagingSettingsDefaults implements StoreSettingsSetup
{
    public function initialize(): void
    {
        $now = CarbonImmutable::now();

        MessagingSettings::query()->insertOrIgnore([
            'id' => 1,
            'has_new_order_alert_emails' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
