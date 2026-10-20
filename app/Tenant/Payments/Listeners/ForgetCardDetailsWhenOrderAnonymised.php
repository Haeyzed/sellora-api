<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Listeners;

use App\Tenant\Orders\Events\OrderAnonymised;
use App\Tenant\Payments\Models\Payment;
use Carbon\CarbonImmutable;

/**
 * Removes the card brand and last four digits from an anonymised order's payments, inside the same PersonalDataErasure run: the payments themselves are financial records and stay, and the database lets card details change only during an erasure.
 */
final readonly class ForgetCardDetailsWhenOrderAnonymised
{
    public function handle(OrderAnonymised $event): void
    {
        Payment::query()
            ->where('order_id', $event->order->id)
            ->where(static fn ($query) => $query->whereNotNull('card_brand')->orWhereNotNull('card_last4'))
            ->toBase()
            ->update(['card_brand' => null, 'card_last4' => null, 'updated_at' => CarbonImmutable::now()]);
    }
}
