<?php

declare(strict_types=1);

namespace App\Shared\Auth;

/**
 * Every permission a person holds and where each one comes from (which role, or given directly), so "why can Ada do this?" is always answerable (section 10).
 */
final readonly class EffectivePermissions
{
    public const string SOURCE_ROLE = 'role';

    public const string SOURCE_DIRECT = 'direct';

    /**
     * @param  list<array{name: string, sources: list<array{type: string, role: string|null}>}>  $permissions  Sorted by name.
     * @param  bool  $holdsEveryPermission  True for a role that may do everything without holding each permission (a store's owner, a platform super admin).
     */
    private function __construct(
        public array $permissions,
        public bool $holdsEveryPermission,
    ) {}

    /**
     * For someone whose role may do everything: every permission, each from that role.
     *
     * @param  list<string>  $allPermissions  Every permission of their guard.
     */
    public static function everythingThrough(string $role, array $allPermissions): self
    {
        sort($allPermissions);

        return new self(
            array_map(static fn (string $name): array => ['name' => $name, 'sources' => [['type' => self::SOURCE_ROLE, 'role' => $role]]], $allPermissions),
            holdsEveryPermission: true,
        );
    }

    /**
     * @param  array<string, list<string>>  $permissionsByRole  Each of their roles' names with the permissions it gives.
     * @param  list<string>  $directPermissions  The permissions given to them directly.
     */
    public static function from(array $permissionsByRole, array $directPermissions): self
    {
        ksort($permissionsByRole);
        $sources = [];

        foreach ($permissionsByRole as $role => $permissionNames) {
            foreach ($permissionNames as $name) {
                $sources[$name][] = ['type' => self::SOURCE_ROLE, 'role' => (string) $role];
            }
        }

        foreach ($directPermissions as $name) {
            $sources[$name][] = ['type' => self::SOURCE_DIRECT, 'role' => null];
        }

        ksort($sources);
        $permissions = [];

        foreach ($sources as $name => $from) {
            $permissions[] = ['name' => (string) $name, 'sources' => $from];
        }

        return new self($permissions, holdsEveryPermission: false);
    }
}
