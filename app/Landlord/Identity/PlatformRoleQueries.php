<?php

declare(strict_types=1);

namespace App\Landlord\Identity;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Reads of the platform roles for the team screens, with how many platform admins have each.
 */
final class PlatformRoleQueries
{
    /**
     * The platform roles, each with its permissions and an "admin_count".
     *
     * @return Builder<Role>
     */
    public static function withAdminCounts(): Builder
    {
        $modelHasRolesTable = config()->string('permission.table_names.model_has_roles');

        return Role::query()
            ->where('guard_name', PlatformAdmin::GUARD)
            ->select('roles.*')
            ->selectSub(
                static fn (QueryBuilder $query) => $query->from($modelHasRolesTable)
                    ->selectRaw('count(*)')
                    ->whereColumn($modelHasRolesTable.'.role_id', 'roles.id')
                    ->where($modelHasRolesTable.'.model_type', (new PlatformAdmin)->getMorphClass()),
                'admin_count',
            )
            ->with('permissions');
    }
}
