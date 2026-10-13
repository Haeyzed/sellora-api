<?php

declare(strict_types=1);

namespace App\Tenant\Inventory;

use Carbon\CarbonImmutable;

/**
 * How long a checkout holds its stock: config/inventory.php, 15 minutes unless changed. For Checkout to pass to ReserveStock.
 */
final class CheckoutHold
{
    public static function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addMinutes(config()->integer('inventory.checkout_hold_minutes'));
    }
}
