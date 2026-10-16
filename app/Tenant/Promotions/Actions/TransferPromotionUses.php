<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Enums\RedemptionStatus;
use App\Tenant\Promotions\Models\PromotionRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Hands a checkout's reserved promotion uses to the order placed from it, for Orders to call (section 6). Safe to run twice.
 *
 * They stay reserved, counting against the promotions' limits, and no longer
 * expire on their own: the order commits them once paid (SettlePromotionUses)
 * or releases them when it is cancelled or not paid by its deadline. A use
 * whose hold had already run out is left for the sweep: someone else may have
 * taken that use meanwhile.
 */
final readonly class TransferPromotionUses
{
    /**
     * @return int How many uses changed holder.
     */
    public function handle(RedemptionHolder $from, RedemptionHolder $to): int
    {
        return DB::transaction(static fn (): int => PromotionRedemption::query()
            ->where('holder_type', $from->type)
            ->where('holder_id', $from->publicId)
            ->where('status', RedemptionStatus::Reserved->value)
            ->where(static fn ($live) => $live->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['holder_type' => $to->type, 'holder_id' => $to->publicId, 'expires_at' => null, 'updated_at' => now()]));
    }
}
