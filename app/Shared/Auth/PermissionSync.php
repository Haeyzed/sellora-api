<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use Illuminate\Support\Facades\Date;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes one guard's permission rows match the permissions defined in code (section 10), in the database currently in use.
 *
 * Safe to run any number of times. A missing permission is created; a row
 * that code no longer defines is removed only once no role and no person
 * holds it, so a permission is never taken from anyone by a deploy. Rows
 * kept that way are reported, to be taken off by hand.
 */
final readonly class PermissionSync
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    /**
     * @param  list<string>  $permissionNames  Every permission the guard has, as defined in code.
     */
    public function sync(string $guard, array $permissionNames): PermissionSyncResult
    {
        $existing = $this->existingNames($guard);
        $created = array_values(array_diff($permissionNames, $existing));
        $notInCode = array_values(array_diff($existing, $permissionNames));

        $this->create($guard, $created);
        $keptInUse = $this->namesStillHeld($guard, $notInCode);
        $removed = array_values(array_diff($notInCode, $keptInUse));

        if ($removed !== []) {
            Permission::query()->where('guard_name', $guard)->whereIn('name', $removed)->delete();
        }

        $this->permissionRegistrar->forgetCachedPermissions();

        sort($created);
        sort($removed);
        sort($keptInUse);

        return new PermissionSyncResult($created, $removed, $keptInUse);
    }

    /**
     * @return list<string>
     */
    private function existingNames(string $guard): array
    {
        return array_values(Permission::query()->where('guard_name', $guard)->pluck('name')->all());
    }

    /**
     * @param  list<string>  $names
     */
    private function create(string $guard, array $names): void
    {
        if ($names === []) {
            return;
        }

        $now = Date::now();

        // Ignores rows another sync running at the same moment has just created (the table is unique on name and guard).
        Permission::query()->insertOrIgnore(array_map(
            static fn (string $name): array => ['name' => $name, 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now],
            $names,
        ));
    }

    /**
     * Which of the permissions a role or a person still holds.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function namesStillHeld(string $guard, array $names): array
    {
        if ($names === []) {
            return [];
        }

        $permissionsTable = config()->string('permission.table_names.permissions');
        $directTable = config()->string('permission.table_names.model_has_permissions');

        return array_values(Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $names)
            ->where(static fn ($query) => $query
                ->has('roles')
                ->orWhereExists(static fn ($direct) => $direct->from($directTable)->whereColumn("{$directTable}.permission_id", "{$permissionsTable}.id")))
            ->pluck('name')
            ->all());
    }
}
