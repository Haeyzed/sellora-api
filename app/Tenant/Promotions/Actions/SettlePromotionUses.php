<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Enums\RedemptionStatus;
use App\Tenant\Promotions\Models\PromotionRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Settles a holder's promotion uses, for Checkout and Orders to call (section 6): committed once its order is paid, or released when its checkout is abandoned or its order cancelled.
 *
 * Each acts only on uses still in the state it expects, so it is safe to run twice.
 */
final readonly class SettlePromotionUses
{
    /**
     * The order was paid: its checkout's reserved uses become the order's, committed and no longer expiring.
     *
     * @return int How many uses were committed.
     */
    public function commit(RedemptionHolder $checkout, RedemptionHolder $order): int
    {
        return DB::transaction(static fn (): int => PromotionRedemption::query()
            ->where('holder_type', $checkout->type)
            ->where('holder_id', $checkout->publicId)
            ->where('status', RedemptionStatus::Reserved->value)
            ->update(['holder_type' => $order->type, 'holder_id' => $order->publicId, 'status' => RedemptionStatus::Committed->value, 'expires_at' => null, 'settled_at' => now(), 'updated_at' => now()]));
    }

    /**
     * The checkout was abandoned or the order cancelled, paid or not: every live use is given back, so it counts against no limit. A refund gives nothing back.
     *
     * @return int How many uses were released.
     */
    public function release(RedemptionHolder $holder): int
    {
        return DB::transaction(static fn (): int => PromotionRedemption::query()
            ->where('holder_type', $holder->type)
            ->where('holder_id', $holder->publicId)
            ->where('status', '<>', RedemptionStatus::Released->value)
            ->update(['status' => RedemptionStatus::Released->value, 'expires_at' => null, 'settled_at' => now(), 'updated_at' => now()]));
    }
}
