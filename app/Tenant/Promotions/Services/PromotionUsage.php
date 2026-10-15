<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Services;

use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCode;
use App\Tenant\Promotions\Models\PromotionRedemption;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * How often a promotion, a code or one buyer has used it, counted from the ledger: committed uses and reserved ones whose hold hasn't run out.
 *
 * A buyer is one person by account or by email together, so signing out and
 * checking out as a guest with the same email counts as the same customer.
 */
final readonly class PromotionUsage
{
    public function isUsedUp(Promotion $promotion, ?PromotionCode $code = null, ?RedemptionHolder $except = null, ?CarbonImmutable $at = null): bool
    {
        if ($promotion->usage_limit !== null && $this->counting($at, $except)->where('promotion_id', $promotion->id)->count() >= $promotion->usage_limit) {
            return true;
        }

        return $code?->usage_limit !== null && $this->counting($at, $except)->where('promotion_code_id', $code->id)->count() >= $code->usage_limit;
    }

    /**
     * Whether the buyer has used the promotion as often as one customer may. A buyer known by neither account nor email can't be counted, so has.
     */
    public function hasBuyerUsedUp(Promotion $promotion, DiscountBuyer $buyer, ?RedemptionHolder $except = null, ?CarbonImmutable $at = null): bool
    {
        if ($promotion->usage_limit_per_customer === null) {
            return false;
        }

        if ($buyer->customer === null && $buyer->email === null) {
            return true;
        }

        $uses = $this->counting($at, $except)->where('promotion_id', $promotion->id)->where(static function (Builder $same) use ($buyer): void {
            if ($buyer->customer !== null) {
                $same->orWhere('customer_id', $buyer->customer->id);
            }

            if ($buyer->email !== null) {
                $same->orWhere('email', $buyer->email);
            }
        });

        return $uses->count() >= $promotion->usage_limit_per_customer;
    }

    /**
     * @return Builder<PromotionRedemption>
     */
    private function counting(?CarbonImmutable $at, ?RedemptionHolder $except): Builder
    {
        $query = PromotionRedemption::query()->counting($at);

        if ($except !== null) {
            $query->whereNot(static fn (Builder $own) => $own->where('holder_type', $except->type)->where('holder_id', $except->publicId));
        }

        return $query;
    }
}
