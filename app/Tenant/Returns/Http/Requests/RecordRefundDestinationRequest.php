<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff recording where the customer asked a return's refund to go, saying how they asked. Needs the returns.manage permission.
 */
final class RecordRefundDestinationRequest extends FormRequest
{
    use ActsAsStaffMember;

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
            /** Where the customer asked it to go: "original_payment", or a key from the customer's list of refund destinations for the return. */
            'destination' => ['required', 'string', 'max:64'],
            /** How the customer asked, such as "Phone call on 3 November". Staff never choose for the customer, so this is required. */
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    public function destination(): string
    {
        return $this->string('destination')->value();
    }

    public function note(): string
    {
        return $this->string('note')->trim()->value();
    }
}
