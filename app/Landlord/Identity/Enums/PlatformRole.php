<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Enums;

/**
 * The built-in roles of Sellora's own team. More roles can be created; these always exist.
 */
enum PlatformRole: string
{
    /** Can do everything on the platform, without needing each permission. */
    case SuperAdmin = 'super_admin';
}
