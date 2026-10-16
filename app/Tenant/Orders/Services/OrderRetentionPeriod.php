<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * How many years the current store keeps personal data on settled orders: its own setting, or its country's default (config/geography.php).
 *
 * Laws on keeping financial records differ by country and change; merchants
 * should confirm their period with their accountant.
 */
final readonly class OrderRetentionPeriod
{
    public function __construct(private FindStoreSettings $findStoreSettings) {}

    public function years(): int
    {
        $settings = $this->findStoreSettings->handle();

        return $settings->order_retention_years ?? $settings->defaultOrderRetentionYears();
    }
}
