<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An order's tags, all of them: the list replaces the current one. Needs the orders.manage permission.
 */
final class ChangeOrderTagsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Labels for finding the order again, such as "gift"; saved in lower case. An empty list removes them all. */
            'tags' => ['present', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'max:40', 'regex:/\S/'],
        ];
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return array_values(array_map(strval(...), (array) $this->validated('tags')));
    }
}
