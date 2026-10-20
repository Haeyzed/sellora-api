<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Looking at an invoice or credit note, or downloading its PDF. Needs the invoices.view permission.
 */
final class ViewInvoicesRequest extends FormRequest
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
        return [];
    }
}
