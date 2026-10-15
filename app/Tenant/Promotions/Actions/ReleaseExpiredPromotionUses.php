<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Tenant\Promotions\Enums\RedemptionStatus;
use App\Tenant\Promotions\Models\PromotionRedemption;

/**
 * Gives back reserved uses whose checkout hold ran out, a batch at a time, for the scheduled sweep. Reserving also does it for the promotion it reserves.
 */
final readonly class ReleaseExpiredPromotionUses
{
    /**
     * @return int How many uses were given back.
     */
    public function handle(int $limit): int
    {
        $ids = PromotionRedemption::query()
            ->where('status', RedemptionStatus::Reserved->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        return PromotionRedemption::query()
            ->whereKey($ids)
            ->where('status', RedemptionStatus::Reserved->value)
            ->update(['status' => RedemptionStatus::Released->value, 'expires_at' => null, 'settled_at' => now(), 'updated_at' => now()]);
    }
}
