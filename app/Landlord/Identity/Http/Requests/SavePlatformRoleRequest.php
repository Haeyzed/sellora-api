<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use App\Landlord\Identity\Enums\PlatformPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * A platform role's name and the complete set of permissions it grants.
 */
final class SavePlatformRoleRequest extends FormRequest
{
    use ActsAsSuperAdmin;

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            /** Unique ignoring case. "super_admin" is reserved. */
            'name' => ['required', 'string', 'min:2', 'max:60'],
            /** Names from the platform permissions list; the complete set the role grants. */
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(PlatformPermission::class)],
        ];
    }

    public function roleName(): string
    {
        return trim($this->string('name')->value());
    }

    /**
     * @return list<PlatformPermission>
     */
    public function permissions(): array
    {
        /** @var list<string> $permissionNames */
        $permissionNames = array_values($this->array('permissions'));

        return array_map(PlatformPermission::from(...), $permissionNames);
    }
}
