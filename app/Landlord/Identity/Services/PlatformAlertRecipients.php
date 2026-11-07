<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Services;

use App\Landlord\Identity\Enums\PlatformAlertKind;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who receives a platform alert: every active platform admin holding its kind's permission (through a role or directly), and every active super admin.
 */
final readonly class PlatformAlertRecipients
{
    /**
     * @return list<PlatformAdmin>
     */
    public function for(PlatformAlertKind $kind): array
    {
        $permission = $kind->permission()->value;

        return array_values(PlatformAdmin::query()
            ->where('is_active', true)
            ->where(static function (Builder $query) use ($permission): void {
                $query->whereHas('roles', static fn (Builder $roles) => $roles->where('name', PlatformRole::SuperAdmin->value))
                    ->orWhereHas('roles.permissions', static fn (Builder $permissions) => $permissions->where('name', $permission))
                    ->orWhereHas('permissions', static fn (Builder $permissions) => $permissions->where('name', $permission));
            })
            ->orderBy('id')
            ->get()
            ->all());
    }
}
