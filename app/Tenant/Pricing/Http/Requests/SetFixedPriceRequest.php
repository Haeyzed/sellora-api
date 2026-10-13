<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Money\Money;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Setting a variant's fixed price in one of the store's currencies. Needs the pricing.manage permission.
 */
final class SetFixedPriceRequest extends FormRequest
{
    use ActsAsStaffMember;

    /** The highest amount accepted, in minor units, as for base prices: safe as a JSON number and in the database. */
    private const int MAX_AMOUNT = 999_999_999_999;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', TaxClass::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The price in the currency's smallest unit, such as 1999 for $19.99 or 1999 for ¥1,999. */
            'price' => ['required', 'integer:strict', 'min:0', 'max:'.self::MAX_AMOUNT],
            /** The "was" price, in the smallest unit, higher than the price; null or left out for none. */
            'compare_at_price' => ['sometimes', 'nullable', 'integer:strict', 'gt:price', 'max:'.self::MAX_AMOUNT],
        ];
    }

    public function price(): Money
    {
        return Money::ofMinor($this->integer('price'), $this->currencyCode());
    }

    public function compareAtPrice(): ?Money
    {
        return $this->filled('compare_at_price') ? Money::ofMinor($this->integer('compare_at_price'), $this->currencyCode()) : null;
    }

    private function currencyCode(): string
    {
        $currency = $this->route('currency');

        return $currency instanceof StoreCurrency ? $currency->currency_code : throw new LogicException('This route must bind a {currency}.');
    }
}
