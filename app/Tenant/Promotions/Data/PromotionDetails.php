<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Shared\Money\Money;
use App\Tenant\Promotions\Enums\PromotionTargetList;

/**
 * What staff set on a promotion, already validated: the fields sent, and the lists that replace the current ones. Null means "leave as it is".
 */
final readonly class PromotionDetails
{
    /**
     * @param  array<string, mixed>  $fields  Model attributes, by name; amounts as Money in the base currency.
     * @param  array<string, string|null>|null  $title  Changed translations, by language; null for a language removes it.
     * @param  list<array{currency: string, discount: Money|null, max_discount: Money|null, min_subtotal: Money|null}>|null  $currencyAmounts  Replaces every exact amount in other currencies.
     * @param  array<'products'|'variants'|'categories', array<value-of<PromotionTargetList>, list<int>>>  $targets  Each kind sent replaces that kind's lists, by database ID.
     * @param  list<int>|null  $customerGroupIds  Replaces the groups it is limited to; empty for everyone.
     */
    public function __construct(
        public array $fields,
        public ?array $title = null,
        public ?array $currencyAmounts = null,
        public array $targets = [],
        public ?array $customerGroupIds = null,
    ) {}

    /**
     * The names of everything changed, for the activity log.
     *
     * @return list<string>
     */
    public function changedFields(): array
    {
        return array_values(array_unique([
            ...array_keys($this->fields),
            ...($this->title === null ? [] : ['title']),
            ...($this->currencyAmounts === null ? [] : ['currency_amounts']),
            ...array_keys($this->targets),
            ...($this->customerGroupIds === null ? [] : ['customer_groups']),
        ]));
    }
}
