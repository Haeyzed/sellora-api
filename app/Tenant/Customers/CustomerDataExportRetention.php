<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use App\Tenant\Customers\Models\CustomerDataExport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes customers' data exports, file first, once they have been expired (or, for one that never finished, requested) for the whole retention period.
 */
final readonly class CustomerDataExportRetention implements RetentionPolicy
{
    public function __construct(private FilesystemFactory $filesystems) {}

    public function periodKey(): string
    {
        return 'customer_data_exports';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $deleted = 0;
        $old = CustomerDataExport::query()->where(static fn (Builder $query) => $query
            ->where('expires_at', '<', $cutoff)
            ->orWhere(static fn (Builder $unfinished) => $unfinished->whereNull('expires_at')->where('created_at', '<', $cutoff)));

        foreach ($old->lazyById(100) as $export) {
            // The file first: a row without its file is harmless, a file without its row would be forgotten.
            if ($export->path !== null) {
                $this->filesystems->disk($export->disk)->delete($export->path);
            }

            $export->delete();
            $deleted++;
        }

        return $deleted;
    }
}
