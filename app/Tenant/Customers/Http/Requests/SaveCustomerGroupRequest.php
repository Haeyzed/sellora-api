<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating a customer group, or changing one (send only what changes). Needs customers.manage.
 */
final class SaveCustomerGroupRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', CustomerGroup::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            /** Unique in the store, ignoring case, such as "Wholesale". */
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            /** For staff only: what the group is for. */
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function groupName(): string
    {
        return trim($this->string('name')->value());
    }

    public function description(): ?string
    {
        return $this->filled('description') ? trim($this->string('description')->value()) : null;
    }

    /**
     * @return array{name?: string, description?: string|null}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('name')) {
            $changes['name'] = $this->groupName();
        }

        if ($this->has('description')) {
            $changes['description'] = $this->description();
        }

        return $changes;
    }
}
