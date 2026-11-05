<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff choosing what an exchange sends in place of each returned line, or how a return is settled. Needs the returns.manage permission.
 */
final class ChangeReturnReplacementsRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ReadsReturnLines;

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
            /** Every line of the return, once each, with the variant to send in its place. */
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            /** The order line's ID. */
            'lines.*.line' => ['required', 'string', 'max:26', 'distinct'],
            /** The ID of the variant to send in place of the line's units, as many of it; it must be for sale and in stock. */
            'lines.*.replacement' => ['required', 'string', 'max:26'],
        ];
    }
}
