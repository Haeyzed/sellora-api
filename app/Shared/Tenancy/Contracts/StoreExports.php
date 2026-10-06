<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Auth\AccountReference;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Exceptions\StoreExportInProgressException;
use App\Shared\Tenancy\Exceptions\StoreExportNotFoundException;
use App\Shared\Tenancy\Exceptions\StoreExportNotReadyException;
use App\Shared\Tenancy\StoreExportDownload;
use App\Shared\Tenancy\StoreExportSummary;

/**
 * Full exports of the current store requested by its owner, while tenancy is initialized for that store.
 *
 * Implemented by Landlord, which keeps every store's exports and their files.
 * The requester is recorded as an account type and public ID, and only that
 * account can see or download the export.
 */
interface StoreExports
{
    /**
     * Queues a new export of the current store.
     *
     * @throws StoreExportInProgressException When another export of the store is still being built.
     * @throws DomainException When the store has nothing to export in its current status.
     */
    public function requestForCurrentStore(AccountReference $requester): StoreExportSummary;

    /**
     * @throws StoreExportNotFoundException When it doesn't exist or the account didn't request it.
     */
    public function findForCurrentStore(string $exportId, AccountReference $requester): StoreExportSummary;

    /**
     * Opens a ready export for download, recording the download.
     *
     * @throws StoreExportNotFoundException When it doesn't exist or the account didn't request it.
     * @throws StoreExportNotReadyException When it is still being built, failed, or has expired.
     */
    public function openForCurrentStore(string $exportId, AccountReference $requester): StoreExportDownload;
}
