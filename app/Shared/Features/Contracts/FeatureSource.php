<?php

declare(strict_types=1);

namespace App\Shared\Features\Contracts;

use App\Shared\Features\FeatureSnapshot;

/**
 * Where Features learns about a store's plan. Implemented by the platform's subscriptions, so Shared never depends on them.
 */
interface FeatureSource
{
    /**
     * The current plan, features and limits of the given store, read fresh from the central database.
     */
    public function snapshotFor(string $tenantId): FeatureSnapshot;
}
