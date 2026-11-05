<?php

declare(strict_types=1);

namespace Tests\Support\Permissions;

use App\Shared\Auth\Contracts\GrantablePermission;

/**
 * A permission with no texts in any language, for proving a missing text fails loudly instead of showing the key.
 */
enum UntranslatedPermission: string implements GrantablePermission
{
    case Untranslated = 'untranslated.sample';

    public function group(): string
    {
        return 'untranslated_group';
    }

    public function isSensitive(): bool
    {
        return false;
    }
}
