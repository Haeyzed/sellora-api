<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A "roles" input: the public IDs of the store's staff roles to give someone.
 *
 * @mixin FormRequest
 */
trait ChoosesStaffRoles
{
    /**
     * @return array<string, list<string|ValidationRule|Exists>>
     */
    protected function roleRules(): array
    {
        return [
            /** Public IDs of the store's roles; an empty list gives no roles. */
            'roles' => ['present', 'array', 'max:20'],
            'roles.*' => ['string', 'distinct', Rule::exists(Role::class, 'public_id')->where('guard_name', StaffMember::GUARD)],
        ];
    }

    /**
     * The chosen roles, with their permissions loaded.
     *
     * @return list<Role>
     */
    public function roles(): array
    {
        $publicIds = $this->validated('roles');

        return array_values(Role::query()
            ->where('guard_name', StaffMember::GUARD)
            ->whereIn('public_id', is_array($publicIds) ? $publicIds : [])
            ->with('permissions')
            ->get()
            ->all());
    }
}
