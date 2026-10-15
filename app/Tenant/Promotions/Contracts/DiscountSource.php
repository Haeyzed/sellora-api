<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Contracts;

use App\Tenant\Promotions\Data\DiscountBasket;
use App\Tenant\Promotions\Data\DiscountProposal;

/**
 * Something that offers discounts on a basket: Promotions itself, and modules such as loyalty points (section 6).
 *
 * Register it in DiscountSourceRegistry from the module's service provider,
 * with the module's feature key so it is used only while the module is
 * enabled for the store. Proposals must be worked out from the basket alone,
 * the same way every time. Gift cards are never a discount source: they are a
 * payment method (Payments), because tax is due on the full price.
 */
interface DiscountSource
{
    /**
     * @return list<DiscountProposal> Every discount it offers for the basket, each taken whole or not at all.
     */
    public function propose(DiscountBasket $basket): array;
}
