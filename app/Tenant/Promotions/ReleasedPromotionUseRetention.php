<?php

declare(strict_types=1);

namespace App\Tenant\Promotions;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Promotions\Enums\RedemptionStatus;
use App\Tenant\Promotions\Models\PromotionRedemption;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes promotion uses that were given back (abandoned checkouts, cancelled orders) once the retention period has passed, in every store.
 *
 * Released uses count against no limit, so nothing depends on them. Reserved
 * and committed uses are never touched: committed ones stay as long as the
 * orders that used them.
 *
 * @extends TimestampRetentionPolicy<PromotionRedemption>
 */
final class ReleasedPromotionUseRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'released_promotion_uses';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<PromotionRedemption>
     */
    protected function query(): Builder
    {
        return PromotionRedemption::query()->where('status', RedemptionStatus::Released->value);
    }

    protected function timestampColumn(): string
    {
        return 'settled_at';
    }
}
