<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Enums\StaffPermission;
use App\Tenant\Identity\StaffPermissionCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * A role's name and the complete set of permissions it grants, when creating or changing it.
 */
final class SaveStaffRoleRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can(StaffPermission::RolesManage->value);
    }

    /**
     * @return array<string, list<string|ValidationRule|In>>
     */
    public function rules(): array
    {
        return [
            /** Unique in the store, ignoring case. "owner" is reserved. */
            'name' => ['required', 'string', 'min:2', 'max:60'],
            /** Names from the permission list; the complete set the role grants. */
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(app(StaffPermissionCatalogue::class)->all())],
        ];
    }

    public function roleName(): string
    {
        return trim($this->string('name')->value());
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $permissions = $this->validated('permissions');

        return is_array($permissions) ? array_values(array_filter($permissions, is_string(...))) : [];
    }
}
