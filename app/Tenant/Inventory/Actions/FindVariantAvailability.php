<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Tenant\Inventory\Enums\StockStatus;

/**
 * Whether each variant can be bought now, for the storefront: its stock isn't counted, some is available at an active location, or it allows backorders.
 *
 * For other domains to call (section 6). Answers yes or no only; quantities
 * are never shown to customers. FindVariantStockStatus also says which of
 * those it is.
 */
final readonly class FindVariantAvailability
{
    public function __construct(
        private FindVariantStockStatus $findVariantStockStatus,
    ) {}

    /**
     * @param  list<int>  $variantIds
     * @return array<int, bool> By variant ID.
     */
    public function handle(array $variantIds): array
    {
        return array_map(static fn (StockStatus $status): bool => $status->canBeBought(), $this->findVariantStockStatus->handle($variantIds));
    }
}
