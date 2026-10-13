<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Money\Decimal;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Setting a manual exchange rate for one of the store's currencies. Needs the pricing.manage permission.
 */
final class SetExchangeRateRequest extends FormRequest
{
    use ActsAsStaffMember;

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
            /** How many units of this currency one unit of the base currency buys, as a decimal string above zero with up to 12 decimal places, such as "0.000645" for NGN to USD. */
            'rate' => ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,12})?$/', 'numeric', 'gt:0'],
        ];
    }

    public function rate(): Decimal
    {
        return Decimal::of($this->string('rate')->value());
    }
}
