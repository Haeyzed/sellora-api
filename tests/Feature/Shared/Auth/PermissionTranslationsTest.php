<?php

declare(strict_types=1);

use App\Landlord\Identity\PlatformPermissionCatalogue;
use App\Shared\Auth\Contracts\GrantablePermission;
use App\Tenant\Identity\StaffPermissionCatalogue;
use Illuminate\Support\Facades\Lang;

/*
 * Section 10: a permission's key is never shown to people, so every
 * permission of both kinds of account has a label and a description, and
 * its group a name, in every language the application has. Checked with
 * Lang::has() for that language alone: __() would quietly return the key, or
 * the English text, for one that is missing.
 */

it('has a label, a description and a group name for every permission, in every language', function (): void {
    $locales = array_map(basename(...), glob(lang_path('*'), GLOB_ONLYDIR) ?: []);
    /** @var list<GrantablePermission> $permissions */
    $permissions = [...app(StaffPermissionCatalogue::class)->permissions(), ...app(PlatformPermissionCatalogue::class)->permissions()];
    $missing = [];

    foreach ($locales as $locale) {
        foreach ($permissions as $permission) {
            foreach (["permissions.{$permission->value}.label", "permissions.{$permission->value}.description", "permission_groups.{$permission->group()}"] as $key) {
                if (! Lang::has($key, $locale, false) || ! is_string(Lang::get($key, [], $locale, false)) || Lang::get($key, [], $locale, false) === '') {
                    $missing[] = "{$locale}: {$key}";
                }
            }
        }
    }

    expect($locales)->toHaveCount(20)
        ->and($permissions)->toHaveCount(42)
        ->and($missing)->toBe([]);
});
