<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Finds a variant by its ID even when it or its product is in the trash or archived, so an order can always show and link to what was bought.
 *
 * For other domains, such as Orders, which copy the name, values, SKU and
 * price onto their own lines at the time of sale and only link back here.
 * Comes with its product (trashed or not) and its values.
 */
final readonly class FindProductVariant
{
    public function handle(string $publicId): ?ProductVariant
    {
        return ProductVariant::withTrashed()
            ->with(['product', 'attributeValues.attribute'])
            ->where('public_id', $publicId)
            ->first();
    }
}
