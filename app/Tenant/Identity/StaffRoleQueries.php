<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Reads of the store's roles for the team screens, with how many staff members have each.
 */
final class StaffRoleQueries
{
    /**
     * The store's roles, each with its permissions and a "staff_count".
     *
     * @return Builder<Role>
     */
    public static function withStaffCounts(): Builder
    {
        $modelHasRolesTable = config()->string('permission.table_names.model_has_roles');

        return Role::query()
            ->where('guard_name', StaffMember::GUARD)
            ->select('roles.*')
            ->selectSub(
                static fn (QueryBuilder $query) => $query->from($modelHasRolesTable)
                    ->selectRaw('count(*)')
                    ->whereColumn($modelHasRolesTable.'.role_id', 'roles.id')
                    ->where($modelHasRolesTable.'.model_type', (new StaffMember)->getMorphClass()),
                'staff_count',
            )
            ->with('permissions');
    }
}
