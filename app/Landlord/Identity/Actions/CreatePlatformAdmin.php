<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use Spatie\Permission\Models\Role;

/**
 * Adds a member of Sellora's team who can sign in to the platform admin app.
 */
final readonly class CreatePlatformAdmin
{
    /**
     * @param  list<PlatformRole>  $roles
     */
    public function handle(string $name, string $email, string $password, array $roles): PlatformAdmin
    {
        return PlatformAdmin::query()->getConnection()->transaction(static function () use ($name, $email, $password, $roles): PlatformAdmin {
            $platformAdmin = PlatformAdmin::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);

            foreach ($roles as $role) {
                $platformAdmin->assignRole(Role::findOrCreate($role->value, PlatformAdmin::GUARD));
            }

            return $platformAdmin;
        });
    }
}
