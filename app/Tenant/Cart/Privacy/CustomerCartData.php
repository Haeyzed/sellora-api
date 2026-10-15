<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Models\CartLine;
use App\Tenant\Customers\Privacy\CustomerDataSubject;

/**
 * A customer's cart: what they meant to buy. Exported as it stands, and deleted on erasure (a cart is no business record).
 *
 * Guest carts belong to nobody known (no email, no account): they are left
 * to the retention purge.
 */
final readonly class CustomerCartData implements PersonalDataHandler
{
    /**
     * @return array{currency: string|null, tax_country_code: string|null, state_code: string|null, shipping_method: string|null, promotion_code: string|null, last_changed_at: string, lines: list<array{product: string, sku: string|null, quantity: int, added_at: string}>}|array{}
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);
        $cart = $customer === null ? null : Cart::query()->where('customer_id', $customer->id)->with(['lines.variant.product', 'shippingMethod'])->first();

        if ($cart === null) {
            return [];
        }

        return [
            'currency' => $cart->currency,
            'tax_country_code' => $cart->destination_country_code,
            'state_code' => $cart->destination_state_code,
            'shipping_method' => $cart->shippingMethod?->getTranslation('name', app()->getLocale()),
            'promotion_code' => $cart->promotion_code,
            'last_changed_at' => $cart->last_activity_at->toIso8601String(),
            'lines' => array_values($cart->lines->map(static fn (CartLine $line): array => [
                'product' => $line->variant->product->getTranslation('name', app()->getLocale()),
                'sku' => $line->variant->sku,
                'quantity' => $line->quantity,
                'added_at' => (string) $line->created_at?->toIso8601String(),
            ])->all()),
        ];
    }

    public function erase(DataSubject $subject): void
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer !== null) {
            Cart::query()->where('customer_id', $customer->id)->delete();
        }
    }
}
