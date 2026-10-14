<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of the store's customer groups, by name, optionally searched. Needs customers.view.
 */
final class ListCustomerGroupsRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', CustomerGroup::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Text to find in the group's name, ignoring case. */
            'search' => ['sometimes', 'string', 'min:1', 'max:100'],
        ];
    }

    /**
     * @param  Builder<CustomerGroup>  $query
     */
    public function applyFilters(Builder $query): void
    {
        $search = $this->validated('search');

        if (is_string($search)) {
            $query->whereRaw('lower(name) like ?', ['%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%']);
        }
    }
}
