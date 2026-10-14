<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Shared\Money\Money;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Data\PromotionDetails;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Services\PromotionActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates a promotion or changes one, with its exact amounts in other currencies, its product, variant and category lists and the customer groups it is limited to.
 *
 * Its method and type are set once, at creation. Everything happens in one
 * transaction; the promotion is audited, and the activity log names which
 * fields changed.
 */
final readonly class SavePromotion
{
    public function __construct(private PromotionActivity $promotionActivity) {}

    public function create(StaffMember $actor, PromotionDetails $details): Promotion
    {
        return DB::transaction(function () use ($actor, $details): Promotion {
            $promotion = new Promotion(['starts_at' => CarbonImmutable::now()]);
            $this->apply($promotion, $details);
            $this->promotionActivity->record($actor, $promotion, 'promotion_created', 'Created');

            return $promotion;
        });
    }

    public function update(StaffMember $actor, Promotion $promotion, PromotionDetails $details): Promotion
    {
        return DB::transaction(function () use ($actor, $promotion, $details): Promotion {
            $this->apply($promotion, $details);
            $this->promotionActivity->record($actor, $promotion, 'promotion_updated', 'Changed', ['fields' => $details->changedFields()]);

            return $promotion;
        });
    }

    private function apply(Promotion $promotion, PromotionDetails $details): void
    {
        $promotion->fill($details->fields);

        if ($details->title !== null) {
            $promotion->changeTranslations('title', $details->title);
        }

        // The amounts share one currency column, which is empty when there are none.
        if (! $promotion->discount instanceof Money && ! $promotion->max_discount instanceof Money && ! $promotion->min_subtotal instanceof Money) {
            $promotion->currency = null;
        }

        $promotion->save();

        if ($details->currencyAmounts !== null) {
            $promotion->currencyAmounts()->delete();

            foreach ($details->currencyAmounts as $amounts) {
                $promotion->currencyAmounts()->create([
                    'currency_code' => $amounts['currency'],
                    'discount' => $amounts['discount'],
                    'max_discount' => $amounts['max_discount'],
                    'min_subtotal' => $amounts['min_subtotal'],
                ]);
            }
        }

        foreach ($details->targets as $kind => $lists) {
            $relation = $promotion->{$kind}();
            $relation->detach();

            foreach ($lists as $list => $ids) {
                $relation->attach(array_fill_keys($ids, ['list' => $list]));
            }
        }

        if ($details->customerGroupIds !== null) {
            $promotion->customerGroups()->sync($details->customerGroupIds);
        }
    }
}
