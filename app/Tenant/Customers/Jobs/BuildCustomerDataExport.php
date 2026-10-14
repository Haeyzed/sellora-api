<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Jobs;

use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Customers\Enums\CustomerDataExportStatus;
use App\Tenant\Customers\Models\CustomerDataExport;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Writes a customer's export of their personal data, from every feature registered in the privacy registry, as one JSON file on a private disk.
 *
 * Runs in the store that dispatched it (tenancy's queue support). An export
 * that is no longer pending (erased meanwhile, or already built) is left alone.
 */
final class BuildCustomerDataExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $exportId)
    {
        $this->onQueue('bulk');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('tenant-bulk-jobs')];
    }

    public function handle(PersonalDataRegistry $registry, FilesystemFactory $filesystems): void
    {
        $export = CustomerDataExport::query()->with('customer')->find($this->exportId);

        if ($export === null || $export->status !== CustomerDataExportStatus::Pending || $export->customer->isErased()) {
            return;
        }

        $path = 'customer-exports/'.$export->public_id.'.json';
        $sections = $registry->export(CustomerDataSubject::of($export->customer));
        $filesystems->disk($export->disk)->put($path, (string) json_encode(['exported_at' => CarbonImmutable::now()->toIso8601String(), ...$sections], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $export->forceFill([
            'status' => CustomerDataExportStatus::Ready,
            'path' => $path,
            'completed_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addHours(config()->integer('customers.data_exports.expire_hours')),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        CustomerDataExport::query()->whereKey($this->exportId)->where('status', CustomerDataExportStatus::Pending->value)->update(['status' => CustomerDataExportStatus::Failed->value]);
    }
}
