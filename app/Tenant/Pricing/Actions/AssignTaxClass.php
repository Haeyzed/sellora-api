<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Pricing\Models\VariantTaxClass;
use Illuminate\Support\Facades\DB;

/**
 * Puts variants in a tax class, or back in the store's default class. Audited per variant.
 */
final readonly class AssignTaxClass
{
    /**
     * @param  TaxClass|null  $taxClass  Null for the store's default class, whichever it is at the time.
     */
    public function toVariant(ProductVariant $variant, ?TaxClass $taxClass): void
    {
        $assignment = VariantTaxClass::query()->where('product_variant_id', $variant->id)->first();

        if ($taxClass === null) {
            $assignment?->delete();

            return;
        }

        if ($assignment === null) {
            $assignment = new VariantTaxClass;
            $assignment->product_variant_id = $variant->id;
        }

        $assignment->tax_class_id = $taxClass->id;
        $assignment->save();
    }

    /**
     * Every variant of a product, including those in the trash, so a restored variant is taxed like its siblings.
     *
     * @param  TaxClass|null  $taxClass  Null for the store's default class.
     */
    public function toProduct(Product $product, ?TaxClass $taxClass): void
    {
        DB::transaction(function () use ($product, $taxClass): void {
            ProductVariant::withTrashed()
                ->where('product_id', $product->id)
                ->orderBy('id')
                ->each(fn (ProductVariant $variant) => $this->toVariant($variant, $taxClass));
        });
    }
}
