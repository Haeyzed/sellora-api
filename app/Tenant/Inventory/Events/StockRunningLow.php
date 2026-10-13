<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A counted variant's available stock at a location fell to or below its low-stock threshold, with some still left. Announced only once the change is saved.
 *
 * Other domains react to it (Messaging for staff alerts, the Engagement
 * module for back-in-stock emails); Inventory never notifies anyone itself.
 */
final class StockRunningLow implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $variantId,
        public readonly int $locationId,
        public readonly int $availableBefore,
        public readonly int $availableAfter,
    ) {}
}
