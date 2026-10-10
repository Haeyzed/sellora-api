<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Tenant\Inventory\Models\StockLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The optional "location" field: an active location's ID, or the store's default location when left out.
 *
 * @mixin FormRequest
 */
trait ChoosesStockLocation
{
    /**
     * @return list<mixed>
     */
    protected function locationRules(): array
    {
        return ['sometimes', 'string', Rule::exists(StockLocation::class, 'public_id')->where('is_active', true)];
    }

    public function stockLocation(): StockLocation
    {
        $location = $this->validated('location');

        return is_string($location)
            ? StockLocation::query()->where('public_id', $location)->sole()
            : StockLocation::query()->where('is_default', true)->sole();
    }
}
