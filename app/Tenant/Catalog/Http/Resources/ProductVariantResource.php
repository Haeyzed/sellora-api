<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Catalog\Models\AttributeValue;
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
    /**
     * The relations every response needs, so none is loaded lazily.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['attributeValues.attribute:id,public_id', 'image:id,uuid'];

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
            /**
             * Its value for each of the product's options, such as Colour "Blue" and Size "M"; empty for a product without options.
             *
             * @var list<array{attribute_id: string, value_id: string, label: array<string, string>}>
             */
            'values' => $this->whenLoaded('attributeValues', static fn (): array => array_values($variant->attributeValues->map(static fn (AttributeValue $value): array => [
                'attribute_id' => $value->attribute->public_id,
                'value_id' => $value->public_id,
                'label' => $value->getTranslations('label'),
            ])->all())),
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            /** Null until the variant is priced; customers can't see or buy an unpriced variant. */
            'price' => $variant->price === null ? null : new MoneyResource($variant->price),
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
            /** The ID of the product image shown for this variant; null when it has none of its own. */
            'image_id' => $this->whenLoaded('image', static fn (): ?string => $variant->image?->uuid),
            /** Its place among the product's variants, from 0. */
            'position' => $variant->position,
            /** When the variant was moved to the trash; null when it isn't in the trash. */
            'trashed_at' => $variant->deleted_at?->toIso8601String(),
        ];
    }
}
