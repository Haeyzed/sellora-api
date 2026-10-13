<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Models\FixedPrice;
use App\Tenant\Pricing\Models\StoreCurrency;

/**
 * Sets or removes a variant's fixed price in one of the store's currencies, used instead of converting its base price (section 3.3). Audited.
 *
 * Only for currencies the store has added; the base price is the variant's
 * own price. A fixed price locks the base currency and tax mode, like any
 * price.
 */
final readonly class SetFixedPrice
{
    /**
     * @param  Money  $price  In the currency, whole minor units, already validated.
     * @param  Money|null  $compareAtPrice  Higher than the price, already validated.
     */
    public function handle(ProductVariant $variant, StoreCurrency $currency, Money $price, ?Money $compareAtPrice): FixedPrice
    {
        $fixedPrice = FixedPrice::query()->where('product_variant_id', $variant->id)->where('currency_code', $currency->currency_code)->first();

        if ($fixedPrice === null) {
            $fixedPrice = new FixedPrice;
            $fixedPrice->product_variant_id = $variant->id;
        }

        $fixedPrice->price = $price;
        $fixedPrice->compare_at_price = $compareAtPrice;
        $fixedPrice->save();

        return $fixedPrice;
    }

    /**
     * Goes back to converting the base price for this currency.
     */
    public function remove(ProductVariant $variant, StoreCurrency $currency): void
    {
        FixedPrice::query()->where('product_variant_id', $variant->id)->where('currency_code', $currency->currency_code)->first()?->delete();
    }
}
