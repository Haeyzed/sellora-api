<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Shared\Money\Money;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff refunding a return. Needs the returns.manage and payments.refund permissions.
 */
final class RefundReturnRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', OrderReturn::class) && $this->actor()->can('refund', Payment::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** Give back shipping too. Left out: true when the reason is damaged or the wrong item, else false. */
            'shipping' => ['sometimes', 'boolean'],
            /** What to keep back from the lines, in minor units of the order's currency, such as when the return breaks a discount's condition; less than the lines are worth. */
            'withheld' => ['required_with:withheld_reason', 'integer', 'min:1'],
            /** Why something is kept back; required with withheld, and only with it. */
            'withheld_reason' => ['required_with:withheld', 'string', 'max:500'],
        ];
    }

    public function shipping(): ?bool
    {
        return $this->has('shipping') ? $this->boolean('shipping') : null;
    }

    public function withheld(OrderReturn $return): ?Money
    {
        return $this->has('withheld') ? Money::ofMinor($this->integer('withheld'), $return->order->currency) : null;
    }

    public function withheldReason(): ?string
    {
        $reason = $this->string('withheld_reason')->trim()->value();

        return $reason === '' ? null : $reason;
    }
}
