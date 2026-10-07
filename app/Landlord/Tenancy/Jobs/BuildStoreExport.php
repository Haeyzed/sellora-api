<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Jobs;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Enums\StoreExportStatus;
use App\Landlord\Tenancy\Models\StoreExport;
use App\Shared\Auth\AccountReference;
use App\Shared\Privacy\StoreDataExporter;
use App\Shared\Tenancy\Contracts\StoreAccountNotifications;
use App\Shared\Tenancy\StoreExportReadyNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds a store export: writes the store's data in its own database's context, zips it, and stores the ZIP on the store's regional disk.
 *
 * Runs on the bulk queue, so a large store never delays urgent work. The
 * working files live in a temporary folder that is always removed, and the
 * export is marked failed if every try fails. Once it is ready, whoever asked
 * for it is emailed.
 */
final class BuildStoreExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $storeExportId)
    {
        $this->onQueue(config()->string('tenancy.store_exports.queue'));
    }

    /**
     * @throws Throwable When writing or storing the export fails; the queue retries it.
     */
    public function handle(StoreDataExporter $storeDataExporter, FilesystemFactory $filesystems, StoreAccountNotifications $storeAccountNotifications): void
    {
        $storeExport = StoreExport::query()->with('tenant')->find($this->storeExportId);

        if ($storeExport === null || ! in_array($storeExport->status, StoreExportStatus::inProgress(), true)) {
            return;
        }

        $storeExport->forceFill(['status' => StoreExportStatus::Building])->save();
        $workingFolder = sys_get_temp_dir().'/sellora-store-export-'.$storeExport->public_id;
        $zipPath = $workingFolder.'.zip';

        try {
            File::ensureDirectoryExists($workingFolder, 0700);
            $storeExport->tenant->run(static fn (): array => $storeDataExporter->writeTo($workingFolder));
            $this->zip($workingFolder, $zipPath);

            $path = "{$storeExport->tenant_id}/{$storeExport->public_id}.zip";
            $this->store($filesystems, $storeExport->disk, $path, $zipPath);

            $storeExport->forceFill([
                'status' => StoreExportStatus::Ready,
                'path' => $path,
                'size_bytes' => filesize($zipPath) ?: null,
                'ready_at' => CarbonImmutable::now(),
            ])->save();
        } finally {
            File::deleteDirectory($workingFolder);
            File::delete($zipPath);
        }

        $this->notifyRequester($storeExport, $storeAccountNotifications);
    }

    /**
     * Emails whoever asked for the export that it is ready. A store's owner is reached through the store, which holds their account.
     *
     * The export is ready whether or not the email goes out (the requester
     * can also check its status), so a failure is reported, never retried:
     * a retry would find the export ready and do nothing.
     */
    private function notifyRequester(StoreExport $storeExport, StoreAccountNotifications $storeAccountNotifications): void
    {
        $store = $storeExport->tenant;

        try {
            $exportUrl = strtr(config()->string("tenancy.store_exports.ready_urls.{$storeExport->requested_by_type}"), [
                '{store}' => $store->public_id,
                '{export}' => $storeExport->public_id,
                '{domain}' => (string) $store->domains()->orderBy('id')->value('domain'),
            ]);
            $notification = new StoreExportReadyNotification($store->name, $storeExport->expires_at, $exportUrl);
            $requester = new AccountReference($storeExport->requested_by_type, $storeExport->requested_by_id);

            if ($requester->type === (new PlatformAdmin)->getMorphClass()) {
                PlatformAdmin::query()->where('public_id', $requester->publicId)->where('is_active', true)->first()?->notify($notification);

                return;
            }

            $store->run(static fn (): bool => $storeAccountNotifications->send($requester, $notification));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Every try failed: the export is marked failed, and a new one can be requested.
     */
    public function failed(?Throwable $exception): void
    {
        StoreExport::query()
            ->whereKey($this->storeExportId)
            ->whereIn('status', StoreExportStatus::inProgress())
            ->update(['status' => StoreExportStatus::Failed, 'failed_at' => CarbonImmutable::now()]);
    }

    /**
     * @throws RuntimeException When the ZIP can't be written.
     */
    private function zip(string $folder, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Can't create {$zipPath}.");
        }

        foreach (File::allFiles($folder) as $file) {
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $file->getRelativePathname()));
        }

        if (! $zip->close()) {
            throw new RuntimeException("Can't write {$zipPath}.");
        }
    }

    /**
     * @throws RuntimeException When the file can't be read or stored.
     */
    private function store(FilesystemFactory $filesystems, string $disk, string $path, string $zipPath): void
    {
        $stream = fopen($zipPath, 'rb') ?: throw new RuntimeException("Can't read {$zipPath}.");

        try {
            if (! $filesystems->disk($disk)->writeStream($path, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException("Can't store the export at {$path}.");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
