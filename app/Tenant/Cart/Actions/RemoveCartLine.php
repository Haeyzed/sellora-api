<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Takes a line out of the shopper's cart, made from the cart version they were looking at.
 */
final readonly class RemoveCartLine
{
    public function __construct(
        private FindCart $findCart,
        private CartChanges $cartChanges,
    ) {}

    /**
     * @throws CartNotFoundException
     * @throws ModelNotFoundException When the line isn't in the shopper's cart.
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     */
    public function handle(Shopper $shopper, string $linePublicId, ?int $expectedVersion): Cart
    {
        return $this->cartChanges->apply($this->findCart->handle($shopper), $expectedVersion, true, static function (Cart $cart) use ($linePublicId): void {
            $cart->lines()->where('public_id', $linePublicId)->firstOrFail()->delete();
        });
    }
}
