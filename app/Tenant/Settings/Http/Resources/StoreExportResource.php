<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Resources;

use App\Shared\Tenancy\StoreExportSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A full export of the store's data, as the owner who requested it sees it.
 *
 * @property StoreExportSummary $resource
 */
final class StoreExportResource extends JsonResource
{
    public function __construct(StoreExportSummary $storeExport)
    {
        parent::__construct($storeExport);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            /** "queued", "building", "ready", "failed" or "expired". */
            'status' => $this->resource->status,
            /** The ZIP's size once ready. */
            'size_bytes' => $this->resource->sizeBytes,
            'requested_at' => $this->resource->requestedAt->toIso8601String(),
            'ready_at' => $this->resource->readyAt?->toIso8601String(),
            /** After this it can no longer be downloaded, and the file is deleted. */
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
        ];
    }
}
