<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Shared\Money\MoneyResource;
use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Data\VariantPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product as customers see it: texts in the language they asked for (or the store's default), and only the variants they can buy.
 *
 * @property Product $resource
 */
final class StorefrontProductResource extends JsonResource
{
    /**
     * The relations every response needs, so none is loaded lazily. Only priced variants outside the trash are ever loaded.
     *
     * @return array<int|string, mixed>
     */
    public static function relations(): array
    {
        return [
            'brand',
            'categories',
            'options',
            'media',
            'variants' => static fn ($variants) => $variants->whereNotNull('price_amount'),
            'variants.attributeValues.attribute:id,public_id',
            'variants.image:id,uuid',
        ];
    }

    /**
     * @param  array<int, bool>  $availability  Whether each variant can be bought now, by variant ID (FindVariantAvailability).
     * @param  array<int, VariantPrice>  $prices  What each variant costs in the currency asked for, by variant ID (VariantPricer); a variant without one shows its base price.
     */
    public function __construct(Product $product, private readonly array $availability = [], private readonly array $prices = [])
    {
        parent::__construct($product);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource;
        $availability = $this->availability;
        $prices = $this->prices;
        $locale = app()->getLocale();
        $description = $product->getTranslation('description', $locale);
        $brand = $product->brand?->trashed() === false ? $product->brand : null;

        return [
            'id' => $product->public_id,
            'slug' => $product->slug,
            /** In the language asked for, or the store's default. */
            'name' => $product->getTranslation('name', $locale),
            'description' => $description === '' ? null : $description,
            /** Null when it has no brand. */
            'brand' => $brand === null ? null : ['id' => $brand->public_id, 'slug' => $brand->slug, 'name' => $brand->getTranslation('name', $locale)],
            /**
             * The IDs of the categories it is listed in.
             *
             * @var list<string>
             */
            'category_ids' => array_values($product->categories->reject(static fn (Category $category): bool => $category->trashed())->map(static fn (Category $category): string => $category->public_id)->all()),
            /**
             * What its variants differ by, such as Size and Colour, in order.
             *
             * @var list<array{id: string, name: string}>
             */
            'options' => array_values($product->options->map(static fn (Attribute $attribute): array => ['id' => $attribute->public_id, 'name' => $attribute->getTranslation('name', $locale)])->all()),
            /** Its images in order; the first is the main one. */
            'images' => StorefrontImageResource::collection($product->getMedia(Product::GALLERY)),
            /**
             * The variants that can be bought: priced, and not in the trash.
             *
             * @var list<array<string, mixed>>
             */
            'variants' => array_values($product->variants->map(static fn (ProductVariant $variant): array => self::variant($variant, $availability, $prices, $locale))->all()),
        ];
    }

    /**
     * @param  array<int, bool>  $availability
     * @param  array<int, VariantPrice>  $prices
     * @return array<string, mixed>
     */
    private static function variant(ProductVariant $variant, array $availability, array $prices, string $locale): array
    {
        $price = $prices[$variant->id] ?? null;
        $unitPrice = $price === null ? $variant->price : $price->unitPrice;
        $compareAtPrice = $price === null ? $variant->compare_at_price : $price->compareAtPrice;

        return [
            'id' => $variant->public_id,
            'values' => array_values($variant->attributeValues->map(static fn (AttributeValue $value): array => [
                'attribute_id' => $value->attribute->public_id,
                'value_id' => $value->public_id,
                'label' => $value->getTranslation('label', $locale),
            ])->all()),
            'sku' => $variant->sku,
            /** In the currency asked for while the store offers it, otherwise the store's own; includes tax in a tax-inclusive store. */
            'price' => $unitPrice === null ? null : new MoneyResource($unitPrice),
            'compare_at_price' => $compareAtPrice === null ? null : new MoneyResource($compareAtPrice),
            /** One of the product's images, by ID; null when it has none of its own. */
            'image_id' => $variant->image?->uuid,
            'requires_shipping' => $variant->requires_shipping,
            /** Whether it can be bought now: in stock, not counted, or open to backorders. Quantities are never shown. */
            'available_for_sale' => $availability[$variant->id] ?? false,
        ];
    }
}
