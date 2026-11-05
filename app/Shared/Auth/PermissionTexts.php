<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Shared\Auth\Contracts\GrantablePermission;
use Illuminate\Translation\Translator;
use LogicException;

/**
 * The words people see for a permission, in the current language: its short label, what it allows, and the name of its group.
 *
 * A permission's key ("orders.view") is a code identifier and is never shown
 * in their place: a missing text fails loudly instead (section 10). A test
 * checks every text exists in every language.
 */
final readonly class PermissionTexts
{
    public function __construct(private Translator $translator) {}

    public function label(GrantablePermission $permission): string
    {
        return $this->text('permissions.'.$permission->value.'.label');
    }

    public function description(GrantablePermission $permission): string
    {
        return $this->text('permissions.'.$permission->value.'.description');
    }

    public function groupLabel(GrantablePermission $permission): string
    {
        return $this->text('permission_groups.'.$permission->group());
    }

    /**
     * @throws LogicException When the text exists in neither the current language nor the fallback.
     */
    private function text(string $key): string
    {
        $text = $this->translator->has($key) ? $this->translator->get($key) : null;

        if (! is_string($text)) {
            throw new LogicException("The permission text [{$key}] is missing.");
        }

        return $text;
    }
}
