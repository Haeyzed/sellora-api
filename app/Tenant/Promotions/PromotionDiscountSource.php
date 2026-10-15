<?php

declare(strict_types=1);

namespace App\Tenant\Promotions;

use App\Tenant\Promotions\Actions\FindPromotionCode;
use App\Tenant\Promotions\Contracts\DiscountSource;
use App\Tenant\Promotions\Data\DiscountBasket;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\Data\LineCategories;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Enums\PromotionStatus;
use App\Tenant\Promotions\Exceptions\CodeNotValidException;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Services\PromotionEvaluator;

/**
 * The store's own promotions as a discount source: every running automatic promotion, and the promotion of the code entered.
 */
final readonly class PromotionDiscountSource implements DiscountSource
{
    private const array RELATIONS = ['products', 'variants', 'categories', 'customerGroups', 'currencyAmounts'];

    public function __construct(
        private PromotionEvaluator $promotionEvaluator,
        private FindPromotionCode $findPromotionCode,
    ) {}

    public function propose(DiscountBasket $basket): array
    {
        $categories = LineCategories::of($basket->lines);
        $proposals = [];

        foreach (Promotion::query()->with(self::RELATIONS)->where('method', PromotionMethod::Automatic->value)->withStatus(PromotionStatus::Running, $basket->at)->orderBy('id')->get() as $promotion) {
            $proposal = $this->promotionEvaluator->evaluate($promotion, $basket, $categories, isCode: false);

            if ($proposal instanceof DiscountProposal) {
                $proposals[] = $proposal;
            }
        }

        $codePromotion = $this->codePromotion($basket);

        if ($codePromotion !== null) {
            $proposal = $this->promotionEvaluator->evaluate($codePromotion, $basket, $categories, isCode: true);

            if ($proposal instanceof DiscountProposal) {
                $proposals[] = $proposal;
            }
        }

        return $proposals;
    }

    /**
     * Why the code entered gives nothing for the basket, or null when it gives something.
     */
    public function codeReason(DiscountBasket $basket): ?string
    {
        $promotion = $this->codePromotion($basket);

        if ($promotion === null) {
            return 'code_not_valid';
        }

        $proposal = $this->promotionEvaluator->evaluate($promotion, $basket, LineCategories::of($basket->lines), isCode: true);

        return is_string($proposal) ? $proposal : null;
    }

    private function codePromotion(DiscountBasket $basket): ?Promotion
    {
        if ($basket->code === null) {
            return null;
        }

        try {
            return $this->findPromotionCode->find($basket->code, $basket->at)->promotion->load(self::RELATIONS);
        } catch (CodeNotValidException) {
            return null;
        }
    }
}
