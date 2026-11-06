<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Storefront\Models\WishlistItem;

/**
 * A customer's wishlist: what they saved to buy later. Exported in full, and deleted on erasure (a wishlist is no business record).
 */
final readonly class WishlistData implements PersonalDataHandler
{
    /**
     * @return list<array{product: string, sku: string|null, saved_at: string}>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return [];
        }

        $items = WishlistItem::query()->where('customer_id', $customer->id)->with(['product', 'variant'])->orderBy('id')->get();

        return array_values($items->map(static fn (WishlistItem $item): array => [
            'product' => $item->product->getTranslation('name', app()->getLocale()),
            'sku' => $item->variant?->sku,
            'saved_at' => $item->created_at->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer !== null) {
            WishlistItem::query()->where('customer_id', $customer->id)->delete();
        }
    }
}
