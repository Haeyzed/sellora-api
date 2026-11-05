<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Services;

use App\Tenant\Cart\Exceptions\VariantNotAvailableException;
use App\Tenant\Cart\Exceptions\VariantOutOfStockException;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Actions\FindVariantStockStatus;
use App\Tenant\Inventory\Enums\StockStatus;

/**
 * Whether variants can go in a cart: for sale (Catalog's rule, ProductVariant::isForSale) and not out of stock, unless they take backorders.
 */
final readonly class CartVariants
{
    public function __construct(
        private FindVariantStockStatus $findVariantStockStatus,
    ) {}

    /**
     * Why the variant can't go in a cart (variant_not_available or variant_out_of_stock), or null when it can.
     */
    public static function whyNotAddable(ProductVariant $variant, StockStatus $stockStatus): ?string
    {
        return match (true) {
            ! $variant->isForSale() => (new VariantNotAvailableException)->errorCode(),
            ! $stockStatus->canBeBought() => (new VariantOutOfStockException)->errorCode(),
            default => null,
        };
    }

    /**
     * @throws VariantNotAvailableException When it isn't for sale.
     * @throws VariantOutOfStockException When it is out of stock and takes no backorders.
     */
    public function ensureCanBeAdded(ProductVariant $variant): void
    {
        if (! $variant->isForSale()) {
            throw new VariantNotAvailableException;
        }

        if (! ($this->findVariantStockStatus->handle([$variant->id])[$variant->id])->canBeBought()) {
            throw new VariantOutOfStockException;
        }
    }
}
