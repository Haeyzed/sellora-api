<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Data;

/**
 * A permission staff roles can grant, from the StaffPermissionCatalogue.
 */
final readonly class StaffPermission
{
    /**
     * @param  string  $name  Such as "customers.view".
     */
    public function __construct(
        public string $name,
    ) {}
}
