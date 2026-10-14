<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Requests;

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Promotions\Data\PromotionDetails;
use App\Tenant\Promotions\Enums\DiscountClass;
use App\Tenant\Promotions\Enums\PromotionAppliesTo;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Enums\PromotionTargetList;
use App\Tenant\Promotions\Enums\PromotionType;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating a promotion, or changing one (send only what changes). Needs the promotions.manage permission.
 *
 * Its method and type are set once, when it is created, and each type takes
 * only its own fields. Amounts are whole minor units in the store's base
 * currency. Dates without an offset are read in the store's timezone.
 */
final class SavePromotionRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesStoreTranslations;

    public const string PERCENT_FORMAT = '/^\d{1,3}(\.\d{1,2})?$/';

    public const int MAX_TARGETS = 500;

    public const int MAX_CURRENCY_AMOUNTS = 20;

    private const array TARGETS = ['products' => Product::class, 'variants' => ProductVariant::class, 'categories' => Category::class];

    private ?StoreSettings $settings = null;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Promotion::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->promotion() === null;
        $type = $this->type();
        $allowedFor = static fn (PromotionType ...$types): bool => $type === null || in_array($type, $types, true);
        $field = static fn (bool $allowed, bool $requiredOnCreate, array $rules): array => $allowed
            ? [$creating && $requiredOnCreate ? 'required' : 'sometimes', ...$rules]
            : ['prohibited'];
        $amount = ['nullable', 'integer:strict', 'min:1'];

        $rules = [
            /** For staff, such as "Black Friday 2026". */
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            /** What customers see next to the discount, in the store's languages, such as {"en": "10% off everything"}. */
            'title' => [$creating ? 'required' : 'sometimes', 'array', $this->storeTranslatedText(100, requiresDefault: true)],
            /** "automatic" applies whenever the basket qualifies; "code" only with one of its codes. Set once. */
            'method' => $creating ? ['required', Rule::enum(PromotionMethod::class)] : ['prohibited'],
            /** What it takes off. Set once. */
            'type' => $creating ? ['required', Rule::enum(PromotionType::class)] : ['prohibited'],
            /** Percentage and fixed amount only: "products" takes it off the eligible lines, "order" off the order after product discounts. */
            'applies_to' => $field($allowedFor(PromotionType::Percentage, PromotionType::FixedAmount), true, [Rule::enum(PromotionAppliesTo::class)]),
            /** Percentage and buy X get Y only: the percentage off as a decimal string, above 0 and up to "100", with up to 2 decimals. "100" makes buy X get Y items free. */
            'percent' => $field($allowedFor(PromotionType::Percentage, PromotionType::BuyXGetY), true, ['string', 'regex:'.self::PERCENT_FORMAT, 'numeric', 'gt:0', 'max:100']),
            /** Fixed amount only: what it takes off, once per order, in the base currency's smallest unit. Never more than the eligible lines cost. */
            'discount' => $field($allowedFor(PromotionType::FixedAmount), true, ['integer:strict', 'min:1']),
            /** The most it takes off, in the base currency's smallest unit; null for no cap. Not for a fixed amount. */
            'max_discount' => $field($allowedFor(PromotionType::Percentage, PromotionType::FreeShipping, PromotionType::BuyXGetY), false, $amount),
            /** What the eligible lines must cost together before discounts, in the base currency's smallest unit; null for no minimum. */
            'min_subtotal' => ['sometimes', ...$amount],
            /** How many eligible items the basket must have; null for no minimum. */
            'min_quantity' => ['sometimes', 'nullable', 'integer:strict', 'min:1'],
            /** Buy X get Y only: how many items to buy. */
            'buy_quantity' => $field($allowedFor(PromotionType::BuyXGetY), true, ['integer:strict', 'min:1', 'max:1000']),
            /** Buy X get Y only: how many items get the discount for each time it applies. */
            'get_quantity' => $field($allowedFor(PromotionType::BuyXGetY), true, ['integer:strict', 'min:1', 'max:1000']),
            /** Buy X get Y only: how many times it repeats in one order at most; null for no limit. */
            'max_applications' => $field($allowedFor(PromotionType::BuyXGetY), false, ['nullable', 'integer:strict', 'min:1']),
            /** Exact amounts in currencies the store offers, used instead of converting the base amounts, like fixed prices. Replaces every one set before. */
            'currency_amounts' => ['sometimes', 'array', 'max:'.self::MAX_CURRENCY_AMOUNTS],
            /** ISO 4217: one of the store's other currencies. */
            'currency_amounts.*.currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/', 'distinct', $this->offeredCurrency(...)],
            /** Fixed amount only: the amount off in this currency's smallest unit. */
            'currency_amounts.*.discount' => $allowedFor(PromotionType::FixedAmount) ? $amount : ['prohibited'],
            'currency_amounts.*.max_discount' => $allowedFor(PromotionType::Percentage, PromotionType::FreeShipping, PromotionType::BuyXGetY) ? $amount : ['prohibited'],
            'currency_amounts.*.min_subtotal' => $amount,
            /** Whether items with a compare-at price (on sale) are eligible. They aren't unless this is true. */
            'includes_sale_items' => ['sometimes', 'boolean:strict'],
            /** Only for a customer's first order, by account or email. */
            'first_order_only' => ['sometimes', 'boolean:strict'],
            /** The other classes of discount it applies together with: "product", "order" and "shipping". A discount applies with another only when both allow it; empty for none. */
            'combines_with' => ['sometimes', 'array'],
            'combines_with.*' => ['string', 'distinct', Rule::enum(DiscountClass::class)],
            /** How many orders may use it in all; null for no limit. */
            'usage_limit' => ['sometimes', 'nullable', 'integer:strict', 'min:1'],
            /** How many orders one customer may use it on, counting their account and their email together; null for no limit. */
            'usage_limit_per_customer' => ['sometimes', 'nullable', 'integer:strict', 'min:1'],
            /** When it starts, such as "2026-11-27T00:00"; without an offset, in the store's timezone. Now when left out. */
            'starts_at' => ['sometimes', 'date'],
            /** When it ends (not included); without an offset, in the store's timezone. Null for no end. */
            'ends_at' => ['sometimes', 'nullable', 'date'],
            /** Whether it starts active. Use activate and deactivate later. */
            'is_active' => $creating ? ['sometimes', 'boolean:strict'] : ['prohibited'],
            /** The customer groups it is limited to, by ID; empty for every customer. Guests are in no group. Replaces the groups set before. */
            'customer_groups' => ['sometimes', 'array', 'max:100'],
            'customer_groups.*' => ['string', 'distinct', Rule::exists(CustomerGroup::class, 'public_id')],
        ];

        foreach (self::TARGETS as $kind => $model) {
            /** Each list sent replaces that list: "include" (with none, everything is), "exclude", and for buy X get Y "get" (with none, the same items as "include"). Categories include their subcategories. */
            $rules[$kind] = ['sometimes', 'array:include,exclude,get'];

            foreach (PromotionTargetList::cases() as $list) {
                $allowed = $list !== PromotionTargetList::Get || $allowedFor(PromotionType::BuyXGetY);
                $rules["{$kind}.{$list->value}"] = $allowed ? ['sometimes', 'array', 'max:'.self::MAX_TARGETS] : ['missing'];
                $rules["{$kind}.{$list->value}.*"] = ['string', 'distinct', Rule::exists($model, 'public_id')->whereNull('deleted_at')];
            }
        }

        return $rules;
    }

    /**
     * The end must come after the start, counting whichever of them isn't being changed.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['starts_at', 'ends_at'])) {
                    return;
                }

                $startsAt = $this->has('starts_at') ? $this->storeDate('starts_at') : ($this->promotion()->starts_at ?? CarbonImmutable::now());
                $endsAt = $this->has('ends_at') ? $this->storeDate('ends_at') : $this->promotion()?->ends_at;

                if ($startsAt !== null && $endsAt !== null && ! $endsAt->greaterThan($startsAt)) {
                    $validator->errors()->add('ends_at', __('validation.after', ['attribute' => 'ends at', 'date' => 'starts at']));
                }
            },
            function (Validator $validator): void {
                foreach ((array) $this->input('currency_amounts', []) as $index => $amounts) {
                    if (is_array($amounts) && ($amounts['discount'] ?? null) === null && ($amounts['max_discount'] ?? null) === null && ($amounts['min_subtotal'] ?? null) === null) {
                        $validator->errors()->add("currency_amounts.{$index}", __('validation.required', ['attribute' => 'an amount']));
                    }
                }
            },
        ];
    }

    public function details(): PromotionDetails
    {
        $fields = [];
        $baseCurrency = $this->settings()->currency_code;
        $money = static fn (mixed $minor): ?Money => is_int($minor) ? Money::ofMinor($minor, $baseCurrency) : null;

        foreach (['name', 'method', 'type', 'applies_to', 'min_quantity', 'buy_quantity', 'get_quantity', 'max_applications', 'includes_sale_items', 'first_order_only', 'usage_limit', 'usage_limit_per_customer', 'is_active'] as $key) {
            if ($this->has($key)) {
                $value = $this->input($key);
                $fields[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        if ($this->has('percent')) {
            $fields['percent'] = $this->filled('percent') ? Decimal::of($this->string('percent')->value()) : null;
        }

        foreach (['discount', 'max_discount', 'min_subtotal'] as $key) {
            if ($this->has($key)) {
                $fields[$key] = $money($this->input($key));
            }
        }

        if ($this->has('combines_with')) {
            $classes = $this->array('combines_with');

            foreach (DiscountClass::cases() as $class) {
                $fields["combines_with_{$class->value}_discounts"] = in_array($class->value, $classes, true);
            }
        }

        foreach (['starts_at', 'ends_at'] as $key) {
            if ($this->has($key)) {
                $fields[$key] = $this->storeDate($key);
            }
        }

        /** @var array<string, string|null>|null $title */
        $title = $this->has('title') ? $this->validated('title') : null;

        return new PromotionDetails($fields, $title, $this->currencyAmounts(), $this->targets(), $this->customerGroupIds());
    }

    /**
     * A date sent, read in the store's timezone when it has no offset, in UTC.
     */
    public function storeDate(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, $this->settings()->timezone)->utc() : null;
    }

    /**
     * @return list<array{currency: string, discount: Money|null, max_discount: Money|null, min_subtotal: Money|null}>|null
     */
    private function currencyAmounts(): ?array
    {
        if (! $this->has('currency_amounts')) {
            return null;
        }

        $amounts = [];

        foreach ($this->array('currency_amounts') as $row) {
            $currency = (string) $row['currency'];
            $money = static fn (mixed $minor): ?Money => is_int($minor) ? Money::ofMinor($minor, $currency) : null;
            $amounts[] = ['currency' => $currency, 'discount' => $money($row['discount'] ?? null), 'max_discount' => $money($row['max_discount'] ?? null), 'min_subtotal' => $money($row['min_subtotal'] ?? null)];
        }

        return $amounts;
    }

    /**
     * @return array<'products'|'variants'|'categories', array<value-of<PromotionTargetList>, list<int>>>
     */
    private function targets(): array
    {
        $targets = [];

        foreach (self::TARGETS as $kind => $model) {
            if (! $this->has($kind)) {
                continue;
            }

            $targets[$kind] = [];

            foreach (PromotionTargetList::cases() as $list) {
                $publicIds = (array) $this->input("{$kind}.{$list->value}", []);
                $targets[$kind][$list->value] = $publicIds === [] ? [] : array_values(array_map(intval(...), $model::query()->whereIn('public_id', $publicIds)->pluck('id')->all()));
            }
        }

        return $targets;
    }

    /**
     * @return list<int>|null
     */
    private function customerGroupIds(): ?array
    {
        if (! $this->has('customer_groups')) {
            return null;
        }

        return array_values(array_map(intval(...), CustomerGroup::query()->whereIn('public_id', $this->array('customer_groups'))->pluck('id')->all()));
    }

    /**
     * One of the store's currencies other than its base currency, which never has a store currency row (AddStoreCurrency refuses it).
     */
    private function offeredCurrency(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! StoreCurrency::query()->where('currency_code', $value)->exists()) {
            $fail(__('validation.custom.currency_amounts.not_offered'));
        }
    }

    private function promotion(): ?Promotion
    {
        $promotion = $this->route('promotion');

        return $promotion instanceof Promotion ? $promotion : null;
    }

    private function type(): ?PromotionType
    {
        $promotion = $this->promotion();

        if ($promotion !== null) {
            return $promotion->type;
        }

        return is_string($this->input('type')) ? PromotionType::tryFrom($this->string('type')->value()) : null;
    }

    private function settings(): StoreSettings
    {
        return $this->settings ??= app(FindStoreSettings::class)->handle();
    }
}
