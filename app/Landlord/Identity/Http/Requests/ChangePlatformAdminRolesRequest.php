<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;

/**
 * The complete new set of a platform admin's roles.
 */
final class ChangePlatformAdminRolesRequest extends FormRequest
{
    use ActsAsSuperAdmin;
    use ChoosesPlatformRoles;

    /**
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        return $this->roleRules();
    }
}
