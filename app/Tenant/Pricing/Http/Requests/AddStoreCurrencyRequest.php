<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Geography\Rules\CurrencyCode;
use App\Shared\Money\Decimal;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding a currency the store sells in. Needs the pricing.manage permission.
 */
final class AddStoreCurrencyRequest extends FormRequest
{
    use ActsAsStaffMember;

    /** A margin from 0 to 25 percent, with up to 4 decimal places, as a string. */
    public const string MARGIN_FORMAT = '/^\d{1,2}(\.\d{1,4})?$/';

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
            /** ISO 4217, such as "USD". Not the store's base currency. */
            'currency' => ['required', 'string', new CurrencyCode],
            /** The margin added to the market rate, a percentage string from "0" to "25" with up to 4 decimal places, such as "2.5"; default "0". */
            'margin_percent' => ['sometimes', 'string', 'regex:'.self::MARGIN_FORMAT, 'numeric', 'max:25'],
        ];
    }

    public function currencyCode(): string
    {
        return $this->string('currency')->value();
    }

    public function marginPercent(): Decimal
    {
        return Decimal::of($this->has('margin_percent') ? $this->string('margin_percent')->value() : '0');
    }
}
