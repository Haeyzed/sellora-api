<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\DiscountOutcome;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Enums\RedemptionStatus;
use App\Tenant\Promotions\Exceptions\PromotionUnavailableException;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCode;
use App\Tenant\Promotions\Models\PromotionRedemption;
use App\Tenant\Promotions\Services\PromotionUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reserves a use of each promotion a checkout got, for Checkout to call (section 6), so a limited promotion is never used more often than allowed.
 *
 * A lock is taken only where a limit needs protecting (section 13): none for
 * an unlimited promotion, so a busy store-wide sale never makes checkouts
 * queue; the promotion's row for a total limit; the code's row for a code's
 * limit; and for a limit per customer, an advisory lock per promotion and
 * buyer (account and email), so different customers never wait for each
 * other. Uses whose hold ran out are given back first. Reserving again for
 * the same holder updates its uses, and gives back those of promotions it no
 * longer gets, so retrying is safe. All or nothing.
 */
final readonly class ReservePromotionUses
{
    /**
     * Serialises checkouts counting the same limit, without blocking the foreign-key share lock every use's insert takes on the promotion, as FOR UPDATE would.
     */
    public const string ROW_LOCK = 'for no key update';

    public function __construct(private PromotionUsage $promotionUsage) {}

    /**
     * @param  DiscountOutcome  $outcome  What the checkout's basket got (ApplyDiscounts).
     * @param  CarbonImmutable  $expiresAt  When the checkout's hold runs out, as its stock hold does.
     * @return list<PromotionRedemption>
     *
     * @throws PromotionUnavailableException When a promotion was used up meanwhile, or the buyer has used it as often as allowed.
     */
    public function handle(RedemptionHolder $holder, DiscountOutcome $outcome, DiscountBuyer $buyer, CarbonImmutable $expiresAt): array
    {
        $applied = array_values(array_filter($outcome->applied, static fn (DiscountProposal $discount): bool => $discount->source === 'promotion'));
        $promotions = Promotion::query()->whereIn('public_id', array_map(static fn (DiscountProposal $discount): string => $discount->reference, $applied))->orderBy('id')->get()->keyBy('public_id');
        $code = $outcome->code === null ? null : PromotionCode::query()->whereRaw('upper(code) = ?', [mb_strtoupper(trim($outcome->code))])->first();

        return DB::transaction(function () use ($holder, $applied, $promotions, $code, $buyer, $expiresAt, $outcome): array {
            $this->releaseDropped($holder, array_values($promotions->map(static fn (Promotion $promotion): int => $promotion->id)->all()));
            $redemptions = [];

            // In promotion order, so two checkouts always take their locks in the same order.
            foreach ($promotions as $promotion) {
                $discount = array_values(array_filter($applied, static fn (DiscountProposal $proposal): bool => $proposal->reference === $promotion->public_id))[0];
                $usedCode = $discount->isCode && $code?->promotion_id === $promotion->id ? $code : null;

                $this->lockLimits($promotion, $usedCode, $buyer);
                self::releaseExpired($promotion);

                if ($this->promotionUsage->isUsedUp($promotion, $usedCode, $holder) || $this->promotionUsage->hasBuyerUsedUp($promotion, $buyer, $holder)) {
                    throw new PromotionUnavailableException(['promotion' => $promotion->public_id]);
                }

                $redemption = PromotionRedemption::query()->where('promotion_id', $promotion->id)->where('holder_type', $holder->type)->where('holder_id', $holder->publicId)->where('status', '<>', RedemptionStatus::Released->value)->first()
                    ?? new PromotionRedemption;
                $redemption->forceFill([
                    'promotion_id' => $promotion->id,
                    'promotion_code_id' => $usedCode?->id,
                    'holder_type' => $holder->type,
                    'holder_id' => $holder->publicId,
                    'customer_id' => $buyer->customer?->id,
                    'email' => $buyer->email,
                    'status' => RedemptionStatus::Reserved,
                    'discount' => $discount->total($outcome->currency),
                    'expires_at' => $expiresAt,
                    'settled_at' => null,
                ])->save();

                $redemptions[] = $redemption;
            }

            return $redemptions;
        });
    }

    /**
     * Takes only the locks the promotion's limits need.
     */
    private function lockLimits(Promotion $promotion, ?PromotionCode $code, DiscountBuyer $buyer): void
    {
        if ($promotion->usage_limit !== null) {
            Promotion::query()->whereKey($promotion->id)->lock(self::ROW_LOCK)->first();
        }

        if ($code?->usage_limit !== null) {
            PromotionCode::query()->whereKey($code->id)->lock(self::ROW_LOCK)->first();
        }

        if ($promotion->usage_limit_per_customer !== null) {
            foreach (self::buyerLockKeys($promotion, $buyer) as $key) {
                DB::select('select pg_advisory_xact_lock(hashtext(?))', [$key]);
            }
        }
    }

    /**
     * The advisory lock keys for one buyer of one promotion, by account and by email, in a fixed order.
     *
     * @return list<string>
     */
    public static function buyerLockKeys(Promotion $promotion, DiscountBuyer $buyer): array
    {
        $keys = [];

        if ($buyer->customer !== null) {
            $keys[] = "promotion-uses:{$promotion->id}:customer:{$buyer->customer->id}";
        }

        if ($buyer->email !== null) {
            $keys[] = "promotion-uses:{$promotion->id}:email:{$buyer->email}";
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param  list<int>  $keptPromotionIds
     */
    private function releaseDropped(RedemptionHolder $holder, array $keptPromotionIds): void
    {
        PromotionRedemption::query()
            ->where('holder_type', $holder->type)
            ->where('holder_id', $holder->publicId)
            ->where('status', RedemptionStatus::Reserved->value)
            ->whereNotIn('promotion_id', $keptPromotionIds)
            ->update(['status' => RedemptionStatus::Released->value, 'expires_at' => null, 'settled_at' => now(), 'updated_at' => now()]);
    }

    private static function releaseExpired(Promotion $promotion): void
    {
        PromotionRedemption::query()
            ->where('promotion_id', $promotion->id)
            ->where('status', RedemptionStatus::Reserved->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => RedemptionStatus::Released->value, 'settled_at' => now(), 'updated_at' => now()]);
    }
}
