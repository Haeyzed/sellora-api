<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\MergedCart;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Data\SkippedLine;
use App\Tenant\Cart\Exceptions\CartFullException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Models\CartLine;
use App\Tenant\Cart\Services\CartChanges;
use App\Tenant\Cart\Services\CartVariants;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Actions\FindVariantStockStatus;
use App\Tenant\Promotions\Actions\FindPromotionCode;
use App\Tenant\Promotions\Exceptions\CodeNotValidException;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Brings what a shopper put in their cart as a guest into their own cart once they sign in, so nothing is lost by signing in.
 *
 * Without a cart of their own, the guest cart simply becomes theirs. With
 * one: a variant in both keeps the larger quantity (never the sum, which
 * would double what was meant once); the guest's other lines come across
 * while they can still be bought and the cart has room, and the rest are
 * listed rather than dropped silently. The customer's code is kept; with
 * none, the guest's is kept if it can still be used. The customer's currency
 * and tax country are kept. The guest cart is then deleted, so its token
 * stops working. Both carts are locked, in a fixed order.
 */
final readonly class MergeGuestCart
{
    public function __construct(
        private FindCart $findCart,
        private FindVariantStockStatus $findVariantStockStatus,
        private FindPromotionCode $findPromotionCode,
    ) {}

    /**
     * @throws CartNotFoundException When the guest token finds no cart, for example because it was already merged.
     */
    public function handle(Customer $customer, ?string $guestToken): MergedCart
    {
        $guest = $this->findCart->handle(Shopper::guest($guestToken));

        try {
            return $guest->getConnection()->transaction(fn (): MergedCart => $this->merge($customer, $guest));
        } catch (UniqueConstraintViolationException) {
            // The customer's own cart was made by another request meanwhile: merge into that one instead.
            return $guest->getConnection()->transaction(fn (): MergedCart => $this->merge($customer, $guest));
        }
    }

    private function merge(Customer $customer, Cart $guest): MergedCart
    {
        $ownId = Cart::query()->where('customer_id', $customer->id)->value('id');
        $locked = Cart::query()->whereKey(array_values(array_filter([$guest->id, $ownId])))->orderBy('id')->lock(CartChanges::ROW_LOCK)->get()->keyBy('id');
        $guest = $locked->get($guest->id) ?? throw new CartNotFoundException;
        $own = $ownId === null ? null : $locked->get($ownId);

        if ($own === null) {
            $guest->forceFill(['customer_id' => $customer->id, 'token_hash' => null])->save();

            return new MergedCart(self::changed($guest), []);
        }

        $skipped = $this->moveLines($guest, $own);
        $own->promotion_code ??= $this->stillUsable($guest->promotion_code);
        $guest->delete();

        return new MergedCart(self::changed($own), $skipped);
    }

    /**
     * @return list<SkippedLine>
     */
    private function moveLines(Cart $guest, Cart $own): array
    {
        $ownLines = $own->lines()->get()->keyBy('product_variant_id');
        $guestLines = $guest->lines()->with('variant.product')->get();
        $stockStatuses = $this->findVariantStockStatus->handle(array_values($guestLines->map(static fn (CartLine $line): int => $line->product_variant_id)->all()));
        $room = config()->integer('cart.max_lines') - $ownLines->count();
        $skipped = [];

        foreach ($guestLines as $line) {
            $existing = $ownLines->get($line->product_variant_id);

            if ($existing !== null) {
                // Both are within the per-line limit already, so the larger is too.
                $existing->update(['quantity' => max($existing->quantity, $line->quantity)]);

                continue;
            }

            $reason = CartVariants::whyNotAddable($line->variant, $stockStatuses[$line->product_variant_id]) ?? ($room > 0 ? null : (new CartFullException($room))->errorCode());

            if ($reason !== null) {
                $skipped[] = new SkippedLine($line->variant, $line->quantity, $reason);

                continue;
            }

            $own->lines()->create($line->only(['product_variant_id', 'quantity', 'seen_price', 'seen_price_source']));
            $room--;
        }

        return $skipped;
    }

    /**
     * The guest's code, when it can still be used; it was already accepted once, so this isn't counted as the shopper trying codes.
     */
    private function stillUsable(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        try {
            return $this->findPromotionCode->find($code)->code;
        } catch (CodeNotValidException) {
            return null;
        }
    }

    private static function changed(Cart $cart): Cart
    {
        $cart->forceFill(['version' => $cart->version + 1, 'last_activity_at' => CarbonImmutable::now()])->save();

        return $cart;
    }
}
