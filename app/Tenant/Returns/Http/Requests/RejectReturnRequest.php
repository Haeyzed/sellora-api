<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff rejecting a return, saying why. Needs the returns.manage permission.
 */
final class RejectReturnRequest extends FormRequest
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
            /** Why; staff see it, and the buyer is told. */
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->value();
    }
}
