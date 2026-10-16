<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A note staff write on an order, for each other. Needs the orders.manage permission.
 */
final class AddOrderNoteRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Order::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Plain text, never shown to the buyer. */
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->value();
    }
}
