<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use Closure;

/**
 * A ready store export, opened for its requester to download.
 */
final readonly class StoreExportDownload
{
    /**
     * @param  Closure(): resource  $openStream  Opens the ZIP for reading only when the response is sent, so it is never held in memory.
     */
    public function __construct(
        public string $fileName,
        public ?int $sizeBytes,
        public Closure $openStream,
    ) {}
}
