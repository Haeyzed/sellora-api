<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\ProductOptionsIncompleteException;
use App\Tenant\Catalog\Exceptions\VariantCombinationTakenException;
use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Catalog\Services\VariantCombinations;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Sets which attributes a product's variants differ by (its options, such as Size and Colour), giving every variant outside the trash its values in the same step.
 *
 * This is how a product with one variant becomes one with many: give it its
 * options and the existing variant its values (keeping its ID, SKU, prices
 * and history, so old orders still point at it), then add the other
 * variants. No variant is ever left without a value for an option, and no
 * two variants end up with the same combination. A product can go back to
 * no options only when it has a single variant. Variants in the trash keep
 * the values they had.
 */
final readonly class ChangeProductOptions
{
    public function __construct(private VariantCombinations $variantCombinations) {}

    /**
     * @param  list<Attribute>  $attributes  The options, in the order customers see them.
     * @param  array<int, list<AttributeValue>>  $valuesByVariantId  Every variant outside the trash, by ID, with its values.
     *
     * @throws ProductOptionsIncompleteException When a variant outside the trash is missing, or its values don't match the options.
     * @throws VariantCombinationTakenException When two variants would have the same combination.
     */
    public function handle(Product $product, array $attributes, array $valuesByVariantId): Product
    {
        return Product::query()->getConnection()->transaction(function () use ($product, $attributes, $valuesByVariantId): Product {
            // Held until commit: no variant can be added or changed in between.
            Product::query()->whereKey($product->id)->lockForUpdate()->first();

            $variants = ProductVariant::query()->where('product_id', $product->id)->orderBy('id')->get();
            $optionAttributeIds = array_map(static fn (Attribute $attribute): int => $attribute->id, $attributes);

            if (array_diff($variants->modelKeys(), array_keys($valuesByVariantId)) !== [] || array_diff(array_keys($valuesByVariantId), $variants->modelKeys()) !== []) {
                throw new ProductOptionsIncompleteException('variants');
            }

            foreach ($valuesByVariantId as $values) {
                $this->variantCombinations->ensureComplete($values, $optionAttributeIds, 'variants');
            }

            $options = [];
            foreach ($attributes as $position => $attribute) {
                $options[$attribute->id] = ['position' => $position];
            }
            $product->auditSync('options', $options, columns: ['attributes.public_id']);
            $product->unsetRelation('options');

            $this->giveVariantsTheirValues(array_values($variants->all()), $valuesByVariantId);

            return $product;
        });
    }

    /**
     * Two passes, so variants can swap combinations ("S" becomes "M" while "M" becomes "S") without tripping the unique index in between.
     *
     * @param  list<ProductVariant>  $variants
     * @param  array<int, list<AttributeValue>>  $valuesByVariantId
     *
     * @throws VariantCombinationTakenException
     */
    private function giveVariantsTheirValues(array $variants, array $valuesByVariantId): void
    {
        foreach ($variants as $variant) {
            $variant->attribute_signature = 'changing:'.$variant->id;
            $variant->saveQuietly();
        }

        foreach ($variants as $variant) {
            $values = $valuesByVariantId[$variant->id];
            $this->variantCombinations->syncValues($variant, $values);
            $variant->attribute_signature = $this->variantCombinations->signatureOf($values);

            try {
                $variant->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw new VariantCombinationTakenException('variants', $exception);
            }
        }
    }
}
