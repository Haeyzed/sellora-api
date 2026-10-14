<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Models\CustomerDataExport;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\DB;

/**
 * The customer's own data exports. Erasure deletes the rows in its transaction, and their files only once it has committed (section 3.3): a rolled-back erasure never loses a file.
 */
final readonly class CustomerDataExportData implements PersonalDataHandler
{
    public function __construct(private FilesystemFactory $filesystems) {}

    /**
     * @return list<array{id: string, status: string, created_at: string, completed_at: string|null}>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return [];
        }

        return array_values($customer->dataExports()->orderBy('id')->get()->map(static fn (CustomerDataExport $export): array => [
            'id' => $export->public_id,
            'status' => $export->status->value,
            'created_at' => $export->created_at->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return;
        }

        $exports = $customer->dataExports()->get();
        $customer->dataExports()->delete();

        $files = $exports->filter(static fn (CustomerDataExport $export): bool => $export->path !== null)
            ->map(static fn (CustomerDataExport $export): array => [$export->disk, (string) $export->path])
            ->values()
            ->all();

        if ($files !== []) {
            $filesystems = $this->filesystems;
            DB::afterCommit(static function () use ($filesystems, $files): void {
                foreach ($files as [$disk, $path]) {
                    $filesystems->disk($disk)->delete($path);
                }
            });
        }
    }
}
