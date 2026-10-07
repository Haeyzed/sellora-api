<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Identity\Enums\StaffPermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Looking at the store's roles, one role, or the list of permissions roles can grant.
 */
final class ViewStaffRolesRequest extends FormRequest
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
        return [];
    }
}
