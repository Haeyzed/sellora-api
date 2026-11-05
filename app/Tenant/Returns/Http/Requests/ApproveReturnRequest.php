<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Returns\Data\FaultConfirmation;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff approving a return. Needs the returns.manage permission, and the payments.refund permission too to let the buyer keep the items, since that refunds without anything coming back.
 */
final class ApproveReturnRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', OrderReturn::class)
            && (! $this->keepItems() || $this->actor()->can('refund', Payment::class));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** Whether the store was at fault; leave it out to confirm what the buyer's reason claims (damaged, wrong item, faulty and not as described claim it was). Only a confirmed fault gives a free like-for-like exchange and free delivery. */
            'stores_fault' => ['sometimes', 'boolean'],
            /** Why, when stores_fault differs from what the reason claims; required then. Not for personal data. */
            'fault_overruled_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            /** Refund without the items coming back: nothing is received or inspected, and no stock moves. */
            'keep_items' => ['sometimes', 'boolean'],
        ];
    }

    public function keepItems(): bool
    {
        return $this->boolean('keep_items');
    }

    /**
     * Whether the store was at fault, as staff say: null confirms what the reason claims.
     */
    public function faultConfirmation(): FaultConfirmation
    {
        $reason = $this->validated('fault_overruled_reason');

        return new FaultConfirmation($this->has('stores_fault') ? $this->boolean('stores_fault') : null, is_string($reason) ? $reason : null);
    }
}
