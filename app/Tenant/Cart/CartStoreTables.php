<?php

declare(strict_types=1);

namespace App\Tenant\Cart;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How carts appear in a store export: they don't. They are short-lived shopper state, and a guest cart's token hash must never leave the store.
 *
 * A customer's own cart is still in their personal data export.
 */
final class CartStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->excludeTable('carts', 'Short-lived shopper state; guest carts are reached with a secret token.');
        $registry->excludeTable('cart_lines', 'Short-lived shopper state, part of the carts left out.');
    }
}
