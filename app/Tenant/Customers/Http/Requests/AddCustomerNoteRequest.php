<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A note staff write about a customer. The customer can ask to see it, in their data export.
 */
final class AddCustomerNoteRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Customer::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Plain text. The customer can ask to see it, in their data export. */
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->value();
    }
}
