<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Shared\Money\Money;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Enums\RefundPurpose;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What to give back on an order: an amount, or lines and shipping, never both. Needs the payments.refund permission.
 */
final class RefundOrderRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('refund', Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** An amount to give back, in minor units of the order's currency; leave out to give back lines or shipping instead. */
            'amount' => ['required_without_all:lines,shipping', 'prohibits:lines,shipping', 'integer:strict', 'min:1', 'max:'.PHP_INT_MAX],
            /** Lines to give back, each at what the buyer paid for it after discounts, tax included. */
            'lines' => ['sometimes', 'array', 'min:1', 'max:100'],
            /** An order line's ID. */
            'lines.*.line' => ['required', 'string', 'max:26', 'distinct'],
            'lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:100000'],
            /** Whether to give back what was charged for shipping, its tax included. */
            'shipping' => ['sometimes', 'boolean:strict', 'accepted'],
            /** The ID of the one payment to give back from; left out, the newest payments go first. A payment in review is refunded only when named, for all it received. */
            'payment' => ['sometimes', 'string', 'max:26'],
            /** Why, for the store's own records. */
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            /** What it does to the sale: "reduces_sale" (returned items, shipping, a price reduction; it gets a credit note) or "returns_payment" (money never part of the sale, such as an overpayment). Left out, lines and shipping reduce the sale, an amount on an order that was sold is a price reduction, and an order never sold only gets its payment back. */
            'purpose' => ['sometimes', Rule::enum(RefundPurpose::class)],
        ];
    }

    public function refundRequest(Order $order): RefundRequest
    {
        $lines = [];

        foreach ((array) $this->validated('lines', []) as $line) {
            /** @var array{line: string, quantity: int} $line */
            $lines[$line['line']] = $line['quantity'];
        }

        return new RefundRequest(
            $this->has('amount') ? Money::ofMinor($this->integer('amount'), $order->currency) : null,
            $lines,
            $this->boolean('shipping'),
            $this->filled('payment') ? $this->string('payment')->value() : null,
            $this->filled('reason') ? $this->string('reason')->trim()->value() : null,
            $this->header('Idempotency-Key'),
            $this->filled('purpose') ? $this->enum('purpose', RefundPurpose::class) : null,
        );
    }
}
