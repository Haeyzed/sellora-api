<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * A page of the store's payments, newest first, optionally narrowed by status, method or mode. Needs the payments.view permission.
 */
final class ListPaymentsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Such as "needs_review" or "unresolved", to find payments staff should look at. */
            'status' => ['sometimes', 'string', Rule::enum(PaymentStatus::class)],
            'method' => ['sometimes', 'string', Rule::enum(PaymentMethod::class)],
            /** Only test payments (true) or only live ones (false). */
            'is_test' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function applyFilters(Builder $query): void
    {
        foreach (['status', 'method'] as $filter) {
            $value = $this->validated($filter);

            if (is_string($value)) {
                $query->where($filter, $value);
            }
        }

        if ($this->has('is_test')) {
            $query->where('is_test', $this->boolean('is_test'));
        }
    }
}
