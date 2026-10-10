<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Resources;

use App\Tenant\Inventory\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A place stock is kept.
 *
 * @property StockLocation $resource
 */
final class StockLocationResource extends JsonResource
{
    public function __construct(StockLocation $location)
    {
        parent::__construct($location);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            /** The store's main location, used whenever no other is chosen. */
            'is_default' => $this->resource->is_default,
        ];
    }
}
