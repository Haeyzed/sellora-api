<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Events;

use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Setting up a new store failed after every retry; the store is now marked as failed and waits for a platform admin to retry it.
 */
final readonly class StoreSetupFailed
{
    use Dispatchable;

    public function __construct(public Tenant $store) {}
}
