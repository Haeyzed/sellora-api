<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Data;

/**
 * What a variant is created with, or what changes about it. Anything left null stays as it is.
 *
 * Amounts are whole minor units of the store's base currency, such as 1999
 * for 19.99.
 */
final readonly class ProductVariantData
{
    /**
     * @param  array{length_mm: int, width_mm: int, height_mm: int}|null  $dimensions
     */
    public function __construct(
        public ?int $priceAmount = null,
        public ?int $compareAtPriceAmount = null,
        public bool $removesCompareAtPrice = false,
        public ?string $sku = null,
        public bool $removesSku = false,
        public ?string $barcode = null,
        public bool $removesBarcode = false,
        public ?bool $requiresShipping = null,
        public ?int $weightGrams = null,
        public bool $removesWeight = false,
        public ?array $dimensions = null,
        public bool $removesDimensions = false,
    ) {}
}
