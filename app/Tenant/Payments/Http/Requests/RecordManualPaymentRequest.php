<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Shared\Money\Money;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What arrived for a payment made by hand: a bank transfer received, or cash collected on delivery. Needs the payments.confirm permission. Recorded once and never edited; a mistake is corrected with a refund.
 */
final class RecordManualPaymentRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('confirm', Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** What arrived, in minor units of the payment's currency (₦1,500.00 is 150000). Less than expected opens a payment for the rest; more is owed back. */
            'amount' => ['required', 'integer:strict', 'min:1', 'max:'.PHP_INT_MAX],
            /** The bank's or the store's own reference for the money, if any. */
            'reference' => ['sometimes', 'nullable', 'string', 'max:128'],
        ];
    }

    public function received(Payment $payment): Money
    {
        return Money::ofMinor($this->integer('amount'), $payment->currency);
    }

    public function reference(): ?string
    {
        return $this->filled('reference') ? $this->string('reference')->trim()->value() : null;
    }
}
