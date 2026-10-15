<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Resources;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use App\Tenant\Promotions\Enums\DiscountClass;
use App\Tenant\Promotions\Enums\PromotionTargetList;
use App\Tenant\Promotions\Enums\RedemptionStatus;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCurrencyAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A promotion, for staff: everything it is set to, its targets by public ID, and where it stands now. Dates are in the store's timezone.
 *
 * @property Promotion $resource
 */
final class PromotionResource extends JsonResource
{
    /** The relations every response needs, for loading them in one go. */
    public const array RELATIONS = ['currencyAmounts', 'products', 'variants', 'categories', 'customerGroups'];

    public function __construct(Promotion $promotion, private readonly string $timezone)
    {
        parent::__construct($promotion);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $promotion = $this->resource;
        $money = static fn (?Money $amount): ?MoneyResource => $amount === null ? null : new MoneyResource($amount);

        return [
            'id' => $promotion->public_id,
            /** For staff. */
            'name' => $promotion->name,
            /** What customers see, by language. */
            'title' => $promotion->getTranslations('title'),
            'method' => $promotion->method->value,
            'type' => $promotion->type->value,
            /** "product", "order" or "shipping": which kind of discount it is, for combining. */
            'discount_class' => $promotion->discountClass()->value,
            'applies_to' => $promotion->applies_to?->value,
            /** As a decimal string, such as "12.50"; null when it isn't a percentage or buy X get Y. */
            'percent' => $promotion->percent?->roundTo(2)->toString(),
            'discount' => $money($promotion->discount),
            'max_discount' => $money($promotion->max_discount),
            'min_subtotal' => $money($promotion->min_subtotal),
            'min_quantity' => $promotion->min_quantity,
            'buy_quantity' => $promotion->buy_quantity,
            'get_quantity' => $promotion->get_quantity,
            'max_applications' => $promotion->max_applications,
            /** Exact amounts in other currencies; any amount missing is converted from the base amount at the checkout's rate. */
            'currency_amounts' => array_values($promotion->currencyAmounts->sortBy('currency_code')->map(static fn (PromotionCurrencyAmount $amounts): array => [
                'currency' => $amounts->currency_code,
                'discount' => $money($amounts->discount),
                'max_discount' => $money($amounts->max_discount),
                'min_subtotal' => $money($amounts->min_subtotal),
            ])->all()),
            'includes_sale_items' => $promotion->includes_sale_items,
            'first_order_only' => $promotion->first_order_only,
            /** @var list<string> */
            'combines_with' => array_map(static fn (DiscountClass $class): string => $class->value, $promotion->combinableClasses()),
            'usage_limit' => $promotion->usage_limit,
            'usage_limit_per_customer' => $promotion->usage_limit_per_customer,
            /** How many paid orders used it. */
            'times_used' => $promotion->redemptions()->where('status', RedemptionStatus::Committed->value)->count(),
            /** How many open checkouts hold a use of it now. With times_used, what counts against its limits. */
            'times_reserved' => $promotion->redemptions()->counting()->where('status', RedemptionStatus::Reserved->value)->count(),
            'starts_at' => $this->local($promotion->starts_at),
            'ends_at' => $this->local($promotion->ends_at),
            'is_active' => $promotion->is_active,
            /** Where it stands now: running, scheduled, ended, inactive or archived. */
            'status' => $promotion->status()->value,
            /** @var array{include: list<string>, exclude: list<string>, get: list<string>} */
            'products' => $this->lists($promotion->products),
            /** @var array{include: list<string>, exclude: list<string>, get: list<string>} */
            'variants' => $this->lists($promotion->variants),
            /** @var array{include: list<string>, exclude: list<string>, get: list<string>} */
            'categories' => $this->lists($promotion->categories),
            /** @var list<string> The customer groups it is limited to; empty for every customer. */
            'customer_groups' => array_values($promotion->customerGroups->pluck('public_id')->sort()->values()->all()),
            'created_at' => $this->local($promotion->created_at),
            'updated_at' => $this->local($promotion->updated_at),
            'archived_at' => $this->local($promotion->deleted_at),
        ];
    }

    private function local(?CarbonImmutable $at): ?string
    {
        return $at?->setTimezone($this->timezone)->toIso8601String();
    }

    /**
     * @param  Collection<int, covariant Model>  $targets
     * @return array{include: list<string>, exclude: list<string>, get: list<string>}
     */
    private function lists(Collection $targets): array
    {
        $lists = ['include' => [], 'exclude' => [], 'get' => []];

        foreach ($targets as $target) {
            /** @var object{list: string} $pivot */
            $pivot = $target->getRelationValue('pivot');
            $lists[PromotionTargetList::from($pivot->list)->value][] = (string) $target->getAttribute('public_id');
        }

        foreach ($lists as $list => $ids) {
            sort($ids);
            $lists[$list] = $ids;
        }

        return $lists;
    }
}
