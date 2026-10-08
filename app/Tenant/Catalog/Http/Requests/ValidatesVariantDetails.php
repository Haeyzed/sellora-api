<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The rules for a variant's price, codes and shipping details, sent at the top level or inside another field such as "variant".
 *
 * Amounts are whole minor units as JSON integers (1999 for 19.99), never
 * decimals or strings (section 11).
 *
 * @mixin FormRequest
 */
trait ValidatesVariantDetails
{
    /** The highest amount accepted, in minor units: safe as a JSON number and in the database. */
    private const int MAX_AMOUNT = 999_999_999_999;

    /**
     * @return list<string>
     */
    protected function priceRules(bool $required): array
    {
        return [$required ? 'required' : 'sometimes', 'integer:strict', 'min:0', 'max:'.self::MAX_AMOUNT];
    }

    /**
     * @return list<string>
     */
    protected function compareAtPriceRules(): array
    {
        return ['sometimes', 'nullable', 'integer:strict', 'min:1', 'max:'.self::MAX_AMOUNT];
    }

    /**
     * @param  ProductVariant|null  $current  The variant being changed, whose own SKU doesn't count as taken.
     * @return list<mixed>
     */
    protected function skuRules(?ProductVariant $current): array
    {
        return [
            'sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
            Rule::unique(ProductVariant::class, 'sku')->whereNull('deleted_at')->ignore($current?->getKey()),
        ];
    }

    /**
     * @return list<string>
     */
    protected function barcodeRules(): array
    {
        return ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'];
    }

    /**
     * @return list<string>
     */
    protected function requiresShippingRules(): array
    {
        return ['sometimes', 'boolean:strict'];
    }

    /**
     * @return list<string>
     */
    protected function weightRules(): array
    {
        return ['sometimes', 'nullable', 'integer:strict', 'min:0', 'max:10000000'];
    }

    /**
     * @return list<string>
     */
    protected function dimensionsRules(): array
    {
        return ['sometimes', 'nullable', 'array:length_mm,width_mm,height_mm'];
    }

    /**
     * One dimension's rules, given the field holding the dimensions, such as "variant.dimensions".
     *
     * @return list<string>
     */
    protected function dimensionRules(string $dimensionsField): array
    {
        return ["required_with:{$dimensionsField}", 'integer:strict', 'min:1', 'max:100000'];
    }

    /**
     * Checks what depends on the variant as a whole, counting what isn't being changed: the compare-at price is above the price, and whatever ships has a weight.
     */
    protected function validateVariantAsAWhole(Validator $validator, string $prefix, ?ProductVariant $current): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $price = $this->has("{$prefix}price") ? $this->integer("{$prefix}price") : $current?->price->minorAmount();
        $compareAtPrice = $this->has("{$prefix}compare_at_price") ? $this->input("{$prefix}compare_at_price") : $current?->compare_at_price?->minorAmount();

        if (is_int($compareAtPrice) && $price !== null && $compareAtPrice <= $price) {
            $validator->errors()->add("{$prefix}compare_at_price", __('validation.custom.compare_at_price.above_price'));
        }

        $requiresShipping = $this->has("{$prefix}requires_shipping") ? $this->boolean("{$prefix}requires_shipping") : ($current->requires_shipping ?? true);
        $weight = $this->has("{$prefix}weight_grams") ? $this->input("{$prefix}weight_grams") : $current?->weight_grams;

        if ($requiresShipping && $weight === null) {
            $validator->errors()->add("{$prefix}weight_grams", __('validation.custom.weight_grams.required_for_shipping'));
        }
    }

    protected function variantData(string $prefix): ProductVariantData
    {
        $nullableInt = fn (string $field): ?int => $this->filled($prefix.$field) ? $this->integer($prefix.$field) : null;
        $sentAsNull = fn (string $field): bool => $this->has($prefix.$field) && $this->input($prefix.$field) === null;
        $nullableString = fn (string $field): ?string => $this->filled($prefix.$field) ? trim($this->string($prefix.$field)->value()) : null;

        /** @var array{length_mm: int, width_mm: int, height_mm: int}|null $dimensions */
        $dimensions = $this->filled("{$prefix}dimensions") ? $this->validated("{$prefix}dimensions") : null;

        return new ProductVariantData(
            priceAmount: $nullableInt('price'),
            compareAtPriceAmount: $nullableInt('compare_at_price'),
            removesCompareAtPrice: $sentAsNull('compare_at_price'),
            sku: $nullableString('sku'),
            removesSku: $sentAsNull('sku'),
            barcode: $nullableString('barcode'),
            removesBarcode: $sentAsNull('barcode'),
            requiresShipping: $this->has("{$prefix}requires_shipping") ? $this->boolean("{$prefix}requires_shipping") : null,
            weightGrams: $nullableInt('weight_grams'),
            removesWeight: $sentAsNull('weight_grams'),
            dimensions: $dimensions,
            removesDimensions: $sentAsNull('dimensions'),
        );
    }
}
