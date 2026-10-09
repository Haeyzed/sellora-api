<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Tenant\Catalog\Exceptions\ProductOptionsIncompleteException;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Which values a variant has, one for each of its product's options, and the signature that keeps every combination unique within a product.
 *
 * The signature lists the attribute and value IDs in attribute order, such as
 * "3:12;5:40", or is empty for the single variant of a product without
 * options. A partial unique index on (product_id, attribute_signature) for
 * variants outside the trash makes two "Blue, M" shirts impossible, even
 * under racing requests.
 */
final readonly class VariantCombinations
{
    /**
     * Checks the values give exactly one value for each of the product's options.
     *
     * @param  list<AttributeValue>  $values
     * @param  list<int>  $optionAttributeIds
     *
     * @throws ProductOptionsIncompleteException When an option has no value or two values, or a value belongs to an attribute that isn't an option.
     */
    public function ensureComplete(array $values, array $optionAttributeIds, string $field = 'values'): void
    {
        $attributeIds = array_map(static fn (AttributeValue $value): int => $value->attribute_id, $values);
        sort($attributeIds);
        sort($optionAttributeIds);

        if ($attributeIds !== $optionAttributeIds) {
            throw new ProductOptionsIncompleteException($field);
        }
    }

    /**
     * @param  list<AttributeValue>  $values
     */
    public function signatureOf(array $values): string
    {
        usort($values, static fn (AttributeValue $first, AttributeValue $second): int => $first->attribute_id <=> $second->attribute_id);

        return implode(';', array_map(static fn (AttributeValue $value): string => $value->attribute_id.':'.$value->id, $values));
    }

    /**
     * Gives a saved variant exactly these values, with an audit of what was added and removed. Its signature is the caller's to set.
     *
     * @param  list<AttributeValue>  $values
     */
    public function syncValues(ProductVariant $variant, array $values): void
    {
        $pivot = [];
        foreach ($values as $value) {
            $pivot[$value->id] = ['attribute_id' => $value->attribute_id];
        }

        $variant->auditSync('attributeValues', $pivot, columns: ['attribute_values.public_id']);
        $variant->unsetRelation('attributeValues');
    }

    /**
     * The attribute IDs of the product's options, in their order.
     *
     * @return list<int>
     */
    public function optionAttributeIdsOf(Product $product): array
    {
        return array_values(array_map(intval(...), $product->options()->pluck('attributes.id')->all()));
    }
}
