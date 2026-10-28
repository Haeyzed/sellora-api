<?php

declare(strict_types=1);

namespace App\Tenant\Delivery;

use App\Shared\Media\StoredFiles;
use App\Shared\Privacy\Contracts\StoreExportFileSource;
use App\Shared\Privacy\ExportedFile;
use App\Tenant\Delivery\Models\Delivery;

/**
 * Proof of delivery in a store export: each delivery's photo and signature as kept, until they are purged.
 */
final class DeliveryStoreFiles implements StoreExportFileSource
{
    /**
     * @return iterable<ExportedFile>
     */
    public function files(): iterable
    {
        return StoredFiles::of([(new Delivery)->getMorphClass()], 'delivery');
    }
}
