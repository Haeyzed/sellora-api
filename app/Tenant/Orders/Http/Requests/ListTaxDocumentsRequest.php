<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of the store's invoices or credit notes, newest first, optionally narrowed by number, order or issue date. Needs the invoices.view permission.
 */
final class ListTaxDocumentsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewInvoices', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** A whole number as printed, such as "INV-2026-000042". */
            'number' => ['sometimes', 'string', 'max:64'],
            /** An order's ID: only its documents. */
            'order' => ['sometimes', 'string', 'max:26'],
            /** Issued on or after this date (YYYY-MM-DD), as printed. */
            'issued_from' => ['sometimes', 'date_format:Y-m-d'],
            /** Issued on or before this date (YYYY-MM-DD), as printed. */
            'issued_to' => ['sometimes', 'date_format:Y-m-d'],
            /** True for test orders' documents only; false for real ones only. */
            'is_test' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  Builder<Invoice>|Builder<CreditNote>  $query
     */
    public function applyFilters(Builder $query): void
    {
        $number = $this->validated('number');
        $order = $this->validated('order');

        if (is_string($number)) {
            $query->where('number', $number);
        }

        if (is_string($order)) {
            $query->whereHas('order', static fn (Builder $orders) => $orders->where('public_id', $order));
        }

        if ($this->filled('issued_from')) {
            $query->where('issued_on', '>=', $this->string('issued_from')->value());
        }

        if ($this->filled('issued_to')) {
            $query->where('issued_on', '<=', $this->string('issued_to')->value());
        }

        if ($this->has('is_test')) {
            $query->where('is_test', $this->boolean('is_test'));
        }
    }
}
