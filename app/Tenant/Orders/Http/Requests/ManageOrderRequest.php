<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing an order: cancelling it, or deleting a note. Needs the orders.manage permission.
 */
final class ManageOrderRequest extends FormRequest
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
        return [];
    }
}
