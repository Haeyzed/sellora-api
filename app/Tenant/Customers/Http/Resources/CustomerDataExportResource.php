<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\CustomerDataExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of a customer's exports of their personal data.
 *
 * @property CustomerDataExport $resource
 */
final class CustomerDataExportResource extends JsonResource
{
    public function __construct(CustomerDataExport $export)
    {
        parent::__construct($export);
    }

    /**
     * @return array{id: string, status: string, downloadable: bool, created_at: string, expires_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            /** pending while it is built, then ready, or failed. */
            'status' => $this->resource->status->value,
            /** Whether "download my data" works for it now. */
            'downloadable' => $this->resource->isDownloadable(),
            'created_at' => $this->resource->created_at->toIso8601String(),
            /** When the download stops working; the file is then deleted. */
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
        ];
    }
}
