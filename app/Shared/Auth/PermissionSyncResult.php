<?php

declare(strict_types=1);

namespace App\Shared\Auth;

/**
 * What one permission sync changed for one guard in one database.
 */
final readonly class PermissionSyncResult
{
    /**
     * @param  list<string>  $created  Permissions defined in code that were missing.
     * @param  list<string>  $removed  Permissions no longer in code, removed because no role or person held them.
     * @param  list<string>  $keptInUse  Permissions no longer in code, kept because a role or person still holds them.
     */
    public function __construct(
        public array $created,
        public array $removed,
        public array $keptInUse,
    ) {}

    public function changedNothing(): bool
    {
        return $this->created === [] && $this->removed === [];
    }
}
