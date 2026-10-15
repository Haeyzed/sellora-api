<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Services;

use App\Tenant\Cart\Exceptions\VariantNotAvailableException;
use App\Tenant\Cart\Exceptions\VariantOutOfStockException;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Actions\FindVariantStockStatus;

/**
 * Whether variants can go in a cart: for sale (published, priced, outside the trash) and not out of stock, unless they take backorders.
 */
final readonly class CartVariants
{
    public function __construct(
        private FindVariantStockStatus $findVariantStockStatus,
    ) {}

    /**
     * Whether customers can buy the variant at all, stock aside (section 3.3).
     */
    public static function isForSale(ProductVariant $variant): bool
    {
        return ! $variant->trashed()
            && $variant->price !== null
            && ! $variant->product->trashed()
            && $variant->product->status === ProductStatus::Active;
    }

    /**
     * @throws VariantNotAvailableException When it isn't for sale.
     * @throws VariantOutOfStockException When it is out of stock and takes no backorders.
     */
    public function ensureCanBeAdded(ProductVariant $variant): void
    {
        if (! self::isForSale($variant)) {
            throw new VariantNotAvailableException;
        }

        if (! ($this->findVariantStockStatus->handle([$variant->id])[$variant->id])->canBeBought()) {
            throw new VariantOutOfStockException;
        }
    }
}
