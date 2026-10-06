<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Enums\StaffPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting one of the store's roles.
 */
final class DeleteStaffRoleRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can(StaffPermission::RolesManage->value);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
