<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Exceptions\VariantSkuTakenException;
use App\Tenant\Catalog\Models\ProductVariant;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Applies a variant's price, codes and shipping details, and saves it, for every action that creates or changes variants.
 */
final readonly class VariantDetails
{
    private const string SKU_INDEX = 'product_variants_sku_unique';

    /**
     * Sets what changes; amounts become Money in the given currency.
     */
    public function apply(ProductVariant $variant, ProductVariantData $details, string $currency): void
    {
        if ($details->priceAmount !== null) {
            $variant->price = Money::ofMinor($details->priceAmount, $currency);
        }

        if ($details->removesCompareAtPrice) {
            $variant->compare_at_price = null;
        } elseif ($details->compareAtPriceAmount !== null) {
            $variant->compare_at_price = Money::ofMinor($details->compareAtPriceAmount, $currency);
        }

        $this->applyCodes($variant, $details);
        $this->applyShipping($variant, $details);
    }

    /**
     * @param  string  $skuField  The input field the SKU was sent in, for the error.
     *
     * @throws VariantSkuTakenException When another variant outside the trash uses the SKU.
     */
    public function save(ProductVariant $variant, string $skuField = 'sku'): void
    {
        try {
            $variant->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), self::SKU_INDEX)) {
                throw new VariantSkuTakenException($skuField, $exception);
            }

            throw $exception;
        }
    }

    private function applyCodes(ProductVariant $variant, ProductVariantData $details): void
    {
        if ($details->removesSku) {
            $variant->sku = null;
        } elseif ($details->sku !== null) {
            $variant->sku = $details->sku;
        }

        if ($details->removesBarcode) {
            $variant->barcode = null;
        } elseif ($details->barcode !== null) {
            $variant->barcode = $details->barcode;
        }
    }

    private function applyShipping(ProductVariant $variant, ProductVariantData $details): void
    {
        if ($details->requiresShipping !== null) {
            $variant->requires_shipping = $details->requiresShipping;
        }

        if ($details->removesWeight) {
            $variant->weight_grams = null;
        } elseif ($details->weightGrams !== null) {
            $variant->weight_grams = $details->weightGrams;
        }

        if ($details->removesDimensions) {
            $variant->length_mm = null;
            $variant->width_mm = null;
            $variant->height_mm = null;
        } elseif ($details->dimensions !== null) {
            $variant->length_mm = $details->dimensions['length_mm'];
            $variant->width_mm = $details->dimensions['width_mm'];
            $variant->height_mm = $details->dimensions['height_mm'];
        }
    }
}
