<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Tenant\Pricing\Data\TaxClassData;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The tax class of each variant: its own, or the store's default. Two queries, however many variants.
 */
final readonly class FindVariantTaxClasses
{
    /**
     * @param  list<int>  $variantIds  Database IDs.
     * @return array<int, TaxClassData> By variant ID.
     *
     * @throws LogicException When the store has no default class, which the tax tables' migration and UpdateTaxClass make impossible.
     */
    public function handle(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $default = TaxClass::query()->default()->first() ?? throw new LogicException('The store has no default tax class.');
        $assigned = DB::table('variant_tax_classes')
            ->join('tax_classes', 'tax_classes.id', '=', 'variant_tax_classes.tax_class_id')
            ->whereIn('variant_tax_classes.product_variant_id', $variantIds)
            ->get(['variant_tax_classes.product_variant_id', 'tax_classes.public_id', 'tax_classes.name', 'tax_classes.is_exempt'])
            ->keyBy('product_variant_id');

        $classes = [];

        foreach ($variantIds as $variantId) {
            $row = $assigned->get($variantId);
            $classes[$variantId] = $row === null
                ? TaxClassData::of($default)
                : new TaxClassData((string) $row->public_id, (string) $row->name, (bool) $row->is_exempt);
        }

        return $classes;
    }
}
