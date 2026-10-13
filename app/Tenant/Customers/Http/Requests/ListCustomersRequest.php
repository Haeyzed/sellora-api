<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of the store's customers, newest first, optionally searched or narrowed, or those in the trash.
 */
final class ListCustomersRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Words to find in the customer's name, email or phone. */
            'search' => ['sometimes', 'string', 'min:2', 'max:100'],
            /** True for active accounts only, false for deactivated ones only. */
            'is_active' => ['sometimes', 'boolean'],
            /** True for customers who confirmed their email, false for those who haven't. */
            'email_verified' => ['sometimes', 'boolean'],
            /** True for the customers in the trash instead of the others. */
            'trashed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function applyFilters(Builder $query): void
    {
        if ($this->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $search = $this->validated('search');

        if (is_string($search)) {
            $query->matching($search);
        }

        if ($this->has('is_active')) {
            $query->where('is_active', $this->boolean('is_active'));
        }

        if ($this->has('email_verified')) {
            $this->boolean('email_verified') ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at');
        }
    }
}
