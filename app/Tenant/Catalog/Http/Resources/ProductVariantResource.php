<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A variant as staff see it: its price, codes and shipping details.
 *
 * @property ProductVariant $resource
 */
final class ProductVariantResource extends JsonResource
{
    public function __construct(ProductVariant $variant)
    {
        parent::__construct($variant);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->resource;

        return [
            'id' => $variant->public_id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => new MoneyResource($variant->price),
            /** The "was" price shown crossed out; null when there is none. */
            'compare_at_price' => $variant->compare_at_price === null ? null : new MoneyResource($variant->compare_at_price),
            'requires_shipping' => $variant->requires_shipping,
            /** In grams. */
            'weight_grams' => $variant->weight_grams,
            /** In millimetres; null when not given. */
            'dimensions' => $variant->length_mm === null ? null : [
                'length_mm' => $variant->length_mm,
                'width_mm' => $variant->width_mm,
                'height_mm' => $variant->height_mm,
            ],
            /** Its place among the product's variants, from 0. */
            'position' => $variant->position,
            /** When the variant was moved to the trash; null when it isn't in the trash. */
            'trashed_at' => $variant->deleted_at?->toIso8601String(),
        ];
    }
}
