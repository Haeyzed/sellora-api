<?php

declare(strict_types=1);

namespace App\Tenant\Catalog;

use App\Shared\Media\StoredFiles;
use App\Shared\Privacy\Contracts\StoreExportFileSource;
use App\Shared\Privacy\ExportedFile;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;

/**
 * The catalog's images in a store export: every product, category and brand image as uploaded, including those of records in the trash.
 */
final class CatalogStoreFiles implements StoreExportFileSource
{
    /**
     * @return iterable<ExportedFile>
     */
    public function files(): iterable
    {
        return StoredFiles::of([(new Product)->getMorphClass(), (new Category)->getMorphClass(), (new Brand)->getMorphClass()], 'catalog');
    }
}
