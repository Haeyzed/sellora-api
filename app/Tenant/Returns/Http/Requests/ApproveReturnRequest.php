<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\Payment;
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
            /** Refund without the items coming back: nothing is received or inspected, and no stock moves. */
            'keep_items' => ['sometimes', 'boolean'],
        ];
    }

    public function keepItems(): bool
    {
        return $this->boolean('keep_items');
    }
}
