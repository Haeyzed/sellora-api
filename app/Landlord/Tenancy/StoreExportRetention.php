<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Models\StoreExport;
use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

/**
 * Deletes store exports 7 days after they were requested (from config), with their files: an export holds every customer's personal data.
 */
final readonly class StoreExportRetention implements RetentionPolicy
{
    public function __construct(private FilesystemFactory $filesystems) {}

    public function periodKey(): string
    {
        return 'store_exports';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Central;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $deleted = 0;

        foreach (StoreExport::query()->where('created_at', '<', $cutoff)->lazyById(100) as $storeExport) {
            $this->delete($storeExport);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * The file first: a row without its file is harmless, a file without its row would be forgotten.
     */
    public function delete(StoreExport $storeExport): void
    {
        if ($storeExport->path !== null) {
            $this->filesystems->disk($storeExport->disk)->delete($storeExport->path);
        }

        $storeExport->delete();
    }
}
