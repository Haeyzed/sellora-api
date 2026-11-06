<?php

declare(strict_types=1);

namespace App\Tenant\Storefront;

use App\Shared\Media\StoredFiles;
use App\Shared\Privacy\Contracts\StoreExportFileSource;
use App\Shared\Privacy\ExportedFile;
use App\Tenant\Storefront\Models\Page;
use App\Tenant\Storefront\Models\StorefrontSettings;

/**
 * The storefront's images (the share image and pages' images) in a store export, as uploaded.
 */
final class StorefrontStoreFiles implements StoreExportFileSource
{
    /**
     * @return iterable<ExportedFile>
     */
    public function files(): iterable
    {
        return StoredFiles::of([(new StorefrontSettings)->getMorphClass(), (new Page)->getMorphClass()], 'storefront');
    }
}
