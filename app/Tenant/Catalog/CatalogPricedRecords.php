<?php

declare(strict_types=1);

namespace App\Tenant\Catalog;

use App\Shared\Money\Contracts\PricedRecords;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Tells Settings whether the catalog holds a price, which locks the store's base currency and tax mode (section 3.3).
 *
 * Only variants with a price count, including those in the trash:
 * restoring one brings its price back, and that price was set in the
 * current currency.
 */
final readonly class CatalogPricedRecords implements PricedRecords
{
    public function exist(): bool
    {
        return ProductVariant::withTrashed()->whereNotNull('price_amount')->exists();
    }
}
