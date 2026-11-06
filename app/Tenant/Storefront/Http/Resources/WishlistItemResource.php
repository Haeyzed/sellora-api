<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Shared\Money\MoneyResource;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Data\VariantPrice;
use App\Tenant\Storefront\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One saved item as its customer sees it: what they saved, whether it is still sold, its price now, and whether it can be bought now. Never a stock quantity.
 *
 * @property WishlistItem $resource
 */
final class WishlistItemResource extends JsonResource
{
    /**
     * The relations every response needs; only priced variants outside the trash are loaded for the product.
     *
     * @return array<int|string, mixed>
     */
    public static function relations(): array
    {
        return [
            'product.media',
            'product.variants' => static fn ($variants) => $variants->whereNotNull('price_amount'),
            'variant',
        ];
    }

    /**
     * @param  array<int, bool>  $availability  By variant ID (FindVariantAvailability).
     * @param  array<int, VariantPrice>  $prices  By variant ID, in the currency asked for (VariantPricer).
     */
    public function __construct(WishlistItem $item, private readonly array $availability = [], private readonly array $prices = [])
    {
        parent::__construct($item);
    }

    /**
     * Whether the item is still for sale: its product is one customers can buy, and its variant (when one was chosen) is priced and outside the trash.
     */
    public static function isForSale(WishlistItem $item): bool
    {
        $product = $item->product;
        $productIsSold = $product->status === ProductStatus::Active && ! $product->trashed() && $product->variants->isNotEmpty();

        return $productIsSold && ($item->variant === null || ($item->variant->price !== null && ! $item->variant->trashed()));
    }

    /**
     * The variants whose price and availability the item shows: its own, or every one of its product's while none was chosen.
     *
     * @return list<ProductVariant>
     */
    public static function shownVariants(WishlistItem $item): array
    {
        if (! self::isForSale($item)) {
            return [];
        }

        return $item->variant === null ? array_values($item->product->variants->all()) : [$item->variant];
    }

    /**
     * Whether the price shown is the lowest of several variants', because no variant was chosen.
     */
    private static function priceIsFrom(WishlistItem $item, int $pricedVariants): bool
    {
        return $item->variant === null && $pricedVariants > 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $product = $item->product;
        $variants = self::shownVariants($item);
        $prices = array_values(array_filter(array_map(fn (ProductVariant $variant): ?VariantPrice => $this->prices[$variant->id] ?? null, $variants)));
        $lowest = array_reduce($prices, static fn (?VariantPrice $lowest, VariantPrice $price): VariantPrice => $lowest === null || $price->unitPrice->isLessThan($lowest->unitPrice) ? $price : $lowest);
        $image = $product->getFirstMedia(Product::GALLERY);
        $priceIsFrom = self::priceIsFrom($item, count($prices));

        return [
            'id' => $item->public_id,
            'added_at' => $item->created_at->toIso8601String(),
            /** What was saved, as it is called now. */
            'product' => [
                'id' => $product->public_id,
                'slug' => $product->slug,
                'name' => $product->getTranslation('name', app()->getLocale()),
                'image' => $image === null ? null : new StorefrontImageResource($image),
            ],
            /** The variant chosen; null when the product was saved before choosing one. */
            'variant' => $item->variant === null ? null : ['id' => $item->variant->public_id, 'sku' => $item->variant->sku],
            /** False once the product (or the variant chosen) is no longer sold: it stays saved until the customer removes it, with no price. */
            'is_for_sale' => self::isForSale($item),
            /** Its price now, in the currency asked for: the lowest of its variants' while none was chosen (see price_is_from). Null while it isn't for sale. */
            'price' => $lowest === null ? null : new MoneyResource($lowest->unitPrice),
            /** True when the price is the lowest among several variants ("from"). */
            'price_is_from' => $priceIsFrom,
            /** Whether it can be bought now: in stock, not counted, or open to backorders. Quantities are never shown. */
            'available_for_sale' => array_any($variants, fn (ProductVariant $variant): bool => $this->availability[$variant->id] ?? false),
        ];
    }
}
