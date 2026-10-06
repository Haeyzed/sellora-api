<?php

declare(strict_types=1);

namespace App\Shared\Privacy\Contracts;

use App\Shared\Privacy\ExportedFile;

/**
 * A feature's uploaded files (product images, documents) to include in a store export, while tenancy is initialized for the store.
 *
 * Registered by each domain that stores files, once domains have them, so a
 * store export always carries the store's files with its data.
 */
interface StoreExportFileSource
{
    /**
     * @return iterable<ExportedFile>
     */
    public function files(): iterable;
}
