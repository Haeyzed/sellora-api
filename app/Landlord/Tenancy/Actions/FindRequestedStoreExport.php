<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Tenancy\Models\StoreExport;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccountReference;
use App\Shared\Tenancy\Exceptions\StoreExportNotFoundException;

/**
 * One of a store's exports, only for the account that requested it. Anyone else is told it doesn't exist.
 */
final readonly class FindRequestedStoreExport
{
    /**
     * @throws StoreExportNotFoundException When it doesn't exist, belongs to another store, or someone else requested it.
     */
    public function handle(Tenant $store, string $exportId, AccountReference $requester): StoreExport
    {
        $storeExport = StoreExport::query()
            ->where('tenant_id', $store->id)
            ->where('public_id', $exportId)
            ->requestedBy($requester)
            ->first();

        return $storeExport ?? throw new StoreExportNotFoundException;
    }
}
