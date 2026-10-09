<?php

declare(strict_types=1);

namespace App\Tenant\Settings;

use App\Shared\Media\StoredFiles;
use App\Shared\Privacy\Contracts\StoreExportFileSource;
use App\Shared\Privacy\ExportedFile;
use App\Tenant\Settings\Models\StoreSettings;

/**
 * The store's logo in a store export, as uploaded.
 */
final class SettingsStoreFiles implements StoreExportFileSource
{
    /**
     * @return iterable<ExportedFile>
     */
    public function files(): iterable
    {
        return StoredFiles::of([(new StoreSettings)->getMorphClass()], 'settings');
    }
}
