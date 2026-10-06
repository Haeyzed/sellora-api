<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A complete set of platform roles, chosen by their IDs.
 *
 * @mixin FormRequest
 */
trait ChoosesPlatformRoles
{
    /**
     * @return array<string, list<string|Exists>>
     */
    protected function roleRules(): array
    {
        return [
            /** IDs from the platform roles list; the complete set. Empty for none. */
            'roles' => ['present', 'array', 'max:20'],
            'roles.*' => ['string', 'distinct', Rule::exists(Role::class, 'public_id')->where('guard_name', PlatformAdmin::GUARD)],
        ];
    }

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        /** @var list<string> $roleIds */
        $roleIds = array_values($this->array('roles'));

        return array_values(Role::query()->where('guard_name', PlatformAdmin::GUARD)->whereIn('public_id', $roleIds)->get()->all());
    }
}
