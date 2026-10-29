<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff starting a return for the buyer. Needs the returns.manage permission. Staff can go past what a buyer could ask for, saying why.
 */
final class StaffReturnRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesReturnRequest;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', OrderReturn::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->returnRules(),
            /** Why units delivered before the store's return window are returned; required for those. */
            'outside_window_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            /** Why units shipped but never marked delivered are returned; required for those. */
            'undelivered_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function outsideWindowReason(): ?string
    {
        $reason = $this->string('outside_window_reason')->trim()->value();

        return $reason === '' ? null : $reason;
    }

    public function undeliveredReason(): ?string
    {
        $reason = $this->string('undelivered_reason')->trim()->value();

        return $reason === '' ? null : $reason;
    }
}
