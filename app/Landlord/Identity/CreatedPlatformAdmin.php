<?php

declare(strict_types=1);

namespace App\Landlord\Identity;

use App\Landlord\Identity\Models\PlatformAdmin;

/**
 * A newly created platform admin, with the recovery codes to show them once.
 */
final readonly class CreatedPlatformAdmin
{
    /**
     * @param  list<string>  $recoveryCodes  In plain text; only their hashes are stored.
     */
    public function __construct(
        public PlatformAdmin $platformAdmin,
        public array $recoveryCodes,
    ) {}
}
