<?php

declare(strict_types=1);

namespace App\Tenant\Returns;

use App\Shared\Media\StoredFiles;
use App\Shared\Privacy\Contracts\StoreExportFileSource;
use App\Shared\Privacy\ExportedFile;
use App\Tenant\Returns\Models\OrderReturn;

/**
 * Return photos in a store export, as kept, until they are purged.
 */
final class ReturnStoreFiles implements StoreExportFileSource
{
    /**
     * @return iterable<ExportedFile>
     */
    public function files(): iterable
    {
        return StoredFiles::of([(new OrderReturn)->getMorphClass()], 'return');
    }
}
