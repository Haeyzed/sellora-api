<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;

/**
 * Empties a cart once its checkout became an order, for Checkout to call inside the transaction that placed it (Step 16): its lines, its code and its shipping choice go, and its version goes up. The cart itself, its currency and where the shopper said they are, stay.
 */
final readonly class ClearOrderedCart
{
    public function __construct(private CartChanges $cartChanges) {}

    public function handle(Cart $cart): Cart
    {
        return $this->cartChanges->apply($cart, null, false, static function (Cart $locked): void {
            $locked->lines()->delete();
            $locked->forceFill(['promotion_code' => null, 'shipping_method_id' => null]);
        });
    }
}
