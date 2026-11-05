<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Services;

use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Actions\FindVariantStockStatus;
use App\Tenant\Returns\Enums\ReturnResolution;
use App\Tenant\Returns\Exceptions\ReturnReplacementInvalidException;
use App\Tenant\Returns\Exceptions\ReturnReplacementNotAvailableException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;

/**
 * The variants an exchange sends in place of the returned units (Step 18d): one per returned line, as many of it, checked the way a cart checks what it can sell (Catalog's for-sale rule, Inventory's stock status), without holding any stock.
 */
final readonly class ReturnReplacements
{
    public function __construct(private FindVariantStockStatus $findVariantStockStatus) {}

    /**
     * Each returned line's replacement for the resolution asked for: a variant for every line of an exchange, none for a refund.
     *
     * @param  list<string>  $lines  The returned order lines' public IDs.
     * @param  array<string, string>  $replacements  Variant public IDs, by order line public ID.
     * @return array<string, ProductVariant|null> By order line public ID.
     *
     * @throws ReturnReplacementInvalidException When a line of an exchange has none, a refund's line has one, or a variant isn't the store's.
     */
    public function resolve(array $lines, ReturnResolution $resolution, array $replacements): array
    {
        $variants = ProductVariant::query()->with('product')->whereIn('public_id', array_values($replacements))->get()->keyBy('public_id');
        $resolved = [];
        $wrong = array_values(array_diff(array_keys($replacements), $lines));

        foreach ($lines as $line) {
            $publicId = $replacements[$line] ?? null;
            $variant = $publicId === null ? null : $variants->get($publicId);

            if (($resolution === ReturnResolution::Exchange) !== ($publicId !== null) || ($publicId !== null && $variant === null)) {
                $wrong[] = $line;
            }

            $resolved[$line] = $variant;
        }

        if ($wrong !== []) {
            throw new ReturnReplacementInvalidException(array_values(array_unique($wrong)));
        }

        return $resolved;
    }

    /**
     * Refuses replacements that can't be sent now: no longer for sale, or out of stock without backorders.
     *
     * @param  array<string, ProductVariant|null>  $replacements  By order line public ID.
     *
     * @throws ReturnReplacementNotAvailableException
     */
    public function ensureAvailable(array $replacements): void
    {
        $variants = array_filter($replacements);
        $statuses = $this->findVariantStockStatus->handle(array_values(array_map(static fn (ProductVariant $variant): int => $variant->id, $variants)));
        $unavailable = [];

        foreach ($variants as $line => $variant) {
            if (! $variant->isForSale() || ! $statuses[$variant->id]->canBeBought()) {
                $unavailable[] = $line;
            }
        }

        if ($unavailable !== []) {
            throw new ReturnReplacementNotAvailableException($unavailable);
        }
    }

    /**
     * The public IDs of the order lines a return sends back, in its own order.
     *
     * @return list<string>
     */
    public function linesOf(OrderReturn $return): array
    {
        return array_values($return->lines()->with('orderLine')->get()->map(static fn (ReturnLine $line): string => $line->orderLine->public_id)->all());
    }

    /**
     * The replacements a return's lines name now, by order line public ID.
     *
     * @return array<string, ProductVariant|null>
     */
    public function of(OrderReturn $return): array
    {
        return $return->lines()->with(['orderLine', 'replacementVariant.product'])->get()
            ->mapWithKeys(static fn (ReturnLine $line): array => [$line->orderLine->public_id => $line->replacementVariant])
            ->all();
    }

    /**
     * Saves each line's replacement (or none) on the return's lines.
     *
     * @param  array<string, ProductVariant|null>  $replacements  By order line public ID.
     */
    public function save(OrderReturn $return, array $replacements): void
    {
        foreach ($return->lines()->with('orderLine')->get() as $line) {
            $line->forceFill(['replacement_variant_id' => $replacements[$line->orderLine->public_id]?->id])->save();
        }
    }
}
