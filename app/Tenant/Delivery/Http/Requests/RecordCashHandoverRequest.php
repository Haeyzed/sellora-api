<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Shared\Money\Money;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff recording cash a driver handed over to them. Needs the delivery.cash.manage permission.
 */
final class RecordCashHandoverRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manageCash', Driver::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** In minor units of the currency, such as kobo; never more than the driver holds in it. */
            'amount' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            /** ISO 4217, such as "NGN". */
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    public function cash(): Money
    {
        return Money::ofMinor($this->integer('amount'), $this->string('currency')->toString());
    }
}
