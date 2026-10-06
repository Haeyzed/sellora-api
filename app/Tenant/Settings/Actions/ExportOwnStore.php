<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Tenancy\Contracts\StoreExports;
use App\Shared\Tenancy\Exceptions\StoreExportInProgressException;
use App\Shared\Tenancy\Exceptions\StoreExportNotFoundException;
use App\Shared\Tenancy\Exceptions\StoreExportNotReadyException;
use App\Shared\Tenancy\StoreExportDownload;
use App\Shared\Tenancy\StoreExportSummary;

/**
 * The owner's full exports of their own store: requesting one, checking it, and downloading it once ready.
 *
 * The platform keeps the exports; only the owner who requested one can see or
 * download it, and every download is recorded.
 */
final readonly class ExportOwnStore
{
    public function __construct(private StoreExports $storeExports) {}

    /**
     * @throws StoreExportInProgressException When another export of the store is still being built.
     */
    public function request(AccountReference $owner): StoreExportSummary
    {
        return $this->storeExports->requestForCurrentStore($owner);
    }

    /**
     * @throws StoreExportNotFoundException When it doesn't exist or the owner didn't request it.
     */
    public function find(string $exportId, AccountReference $owner): StoreExportSummary
    {
        return $this->storeExports->findForCurrentStore($exportId, $owner);
    }

    /**
     * @throws StoreExportNotFoundException When it doesn't exist or the owner didn't request it.
     * @throws StoreExportNotReadyException When it is still being built, failed, or has expired.
     */
    public function open(string $exportId, AccountReference $owner): StoreExportDownload
    {
        return $this->storeExports->openForCurrentStore($exportId, $owner);
    }
}
