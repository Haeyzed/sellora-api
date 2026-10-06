<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Resources;

use App\Shared\Tenancy\ClosedStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The store just closed: when, and when its data will be deleted for good.
 *
 * @property ClosedStore $resource
 */
final class ClosedStoreResource extends JsonResource
{
    public function __construct(ClosedStore $closedStore)
    {
        parent::__construct($closedStore);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'closed_at' => $this->resource->closedAt->toIso8601String(),
            /** After this date the store's data is deleted for good. Until then, Sellora support can restore the store. */
            'purge_after' => $this->resource->purgeAfter->toIso8601String(),
        ];
    }
}
