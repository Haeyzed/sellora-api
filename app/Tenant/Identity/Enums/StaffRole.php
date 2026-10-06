<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Enums;

/**
 * The built-in staff roles every store has. Owners can create more; these always exist.
 */
enum StaffRole: string
{
    /** The person who runs the store: can do everything in it, without needing each permission. */
    case Owner = 'owner';
}
