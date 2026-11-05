<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Identity\Enums\StaffPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Listing the permissions a store's roles can grant, optionally searched by label, description or key.
 */
final class ListStaffPermissionsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can(StaffPermission::RolesView->value);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Text to look for in each permission's label or description (in the dashboard's language) or its key, ignoring case. */
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function search(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) ? $search : null;
    }
}
