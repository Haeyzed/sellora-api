<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Actions\FindRequestedStoreExport;
use App\Landlord\Tenancy\Actions\OpenStoreExport;
use App\Landlord\Tenancy\Actions\RequestStoreExport;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Models\StoreExport;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccountReference;
use App\Shared\Tenancy\Contracts\StoreExports;
use App\Shared\Tenancy\Exceptions\StoreExportInProgressException;
use App\Shared\Tenancy\Exceptions\StoreExportNotFoundException;
use App\Shared\Tenancy\Exceptions\StoreExportNotReadyException;
use App\Shared\Tenancy\StoreExportDownload;
use App\Shared\Tenancy\StoreExportSummary;
use LogicException;

/**
 * The current store's exports for its owner, through the same Actions platform admins use.
 *
 * Each call runs with tenancy ended, so the export records, the queued job
 * and the activity log all stay in the central database.
 */
final readonly class PlatformStoreExports implements StoreExports
{
    public function __construct(
        private RequestStoreExport $requestStoreExport,
        private FindRequestedStoreExport $findRequestedStoreExport,
        private OpenStoreExport $openStoreExport,
    ) {}

    /**
     * @throws StoreStatusConflictException
     * @throws StoreExportInProgressException
     */
    public function requestForCurrentStore(AccountReference $requester): StoreExportSummary
    {
        $store = $this->currentStore();

        return self::summaryOf(tenancy()->central(fn (): StoreExport => $this->requestStoreExport->handle($store, $requester)));
    }

    /**
     * @throws StoreExportNotFoundException
     */
    public function findForCurrentStore(string $exportId, AccountReference $requester): StoreExportSummary
    {
        $store = $this->currentStore();

        return self::summaryOf(tenancy()->central(fn (): StoreExport => $this->findRequestedStoreExport->handle($store, $exportId, $requester)));
    }

    /**
     * @throws StoreExportNotFoundException
     * @throws StoreExportNotReadyException
     */
    public function openForCurrentStore(string $exportId, AccountReference $requester): StoreExportDownload
    {
        $store = $this->currentStore();

        return tenancy()->central(fn (): StoreExportDownload => $this->openStoreExport->handle(
            $this->findRequestedStoreExport->handle($store, $exportId, $requester),
            $requester,
        ));
    }

    public static function summaryOf(StoreExport $storeExport): StoreExportSummary
    {
        return new StoreExportSummary(
            $storeExport->public_id,
            $storeExport->currentStatus()->value,
            $storeExport->size_bytes,
            $storeExport->created_at ?? $storeExport->expires_at,
            $storeExport->ready_at,
            $storeExport->expires_at,
        );
    }

    /**
     * @throws LogicException When no store is current, which is a programming mistake.
     */
    private function currentStore(): Tenant
    {
        $store = tenant();

        return $store instanceof Tenant ? $store : throw new LogicException('Store exports for the owner need the store to be current.');
    }
}
