<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A variant's available stock at a location changed, such as a receipt, a sale or a reservation. Announced only once the change is saved.
 *
 * Other domains react to it (Messaging for staff alerts, the Engagement
 * module for back-in-stock emails); Inventory never notifies anyone itself.
 */
final class StockLevelChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $variantId,
        public readonly int $locationId,
        public readonly int $availableBefore,
        public readonly int $availableAfter,
    ) {}
}
