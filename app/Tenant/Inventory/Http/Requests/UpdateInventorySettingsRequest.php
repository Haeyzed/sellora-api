<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Shared\Money\Decimal;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Inventory\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing how a variant's stock is handled, which needs the inventory.adjust permission (and inventory.costs for its unit cost). Send only what changes.
 */
final class UpdateInventorySettingsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('update', StockItem::class)
            && (! $this->has('unit_cost') || $this->actor()->can('viewCosts', StockItem::class));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** False for things never counted, such as made-to-order items: always available to buy. */
            'tracks_stock' => ['sometimes', 'boolean:strict'],
            /** Whether it may still be ordered when none is available. Turning it off keeps orders already taken beyond the stock. */
            'allows_backorder' => ['sometimes', 'boolean:strict'],
            /** Its own low-stock threshold; null uses the store's. */
            'low_stock_threshold' => ['sometimes', 'nullable', 'integer:strict', 'min:0', 'max:1000000'],
            /** What one unit costs the store, in its base currency, as a decimal string with up to 6 decimal places, such as "4.25". Null removes it. Needs the inventory.costs permission. */
            'unit_cost' => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,14}(\.\d{1,6})?$/'],
        ];
    }

    public function changes(): InventorySettingsData
    {
        return new InventorySettingsData(
            tracksStock: $this->has('tracks_stock') ? $this->boolean('tracks_stock') : null,
            allowsBackorder: $this->has('allows_backorder') ? $this->boolean('allows_backorder') : null,
            lowStockThreshold: $this->filled('low_stock_threshold') ? $this->integer('low_stock_threshold') : null,
            usesStoreThreshold: $this->has('low_stock_threshold') && $this->input('low_stock_threshold') === null,
            unitCost: $this->filled('unit_cost') ? Decimal::of($this->string('unit_cost')->value()) : null,
            removesUnitCost: $this->has('unit_cost') && $this->input('unit_cost') === null,
        );
    }
}
