<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * An order's corrected contact details, all of them. Needs the orders.manage permission.
 */
final class CorrectOrderContactRequest extends FormRequest
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
            /** Where the store writes to about this order from now on. It never links the order to an account. */
            'email' => ['required', 'string', 'email', 'max:254'],
            'name' => ['nullable', 'string', 'max:200'],
            /** In international format, such as +2348012345678. Stored in E.164. */
            'phone' => ['nullable', 'string', 'max:30', (new Phone)->international()],
        ];
    }

    public function email(): string
    {
        return mb_strtolower($this->string('email')->trim()->value());
    }

    public function name(): ?string
    {
        return $this->filled('name') ? $this->string('name')->trim()->value() : null;
    }

    public function phone(): ?string
    {
        return $this->filled('phone') ? (new PhoneNumber($this->string('phone')->value()))->formatE164() : null;
    }
}
