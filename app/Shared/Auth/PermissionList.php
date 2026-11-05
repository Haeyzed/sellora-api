<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Shared\Auth\Contracts\GrantablePermission;
use App\Shared\Auth\Contracts\GrantablePermissions;
use Collator;
use Illuminate\Translation\Translator;
use LogicException;

/**
 * The permissions a list endpoint shows: optionally only those matching a search, sorted as dashboards list them, by group and then label, in the dashboard's language.
 */
final readonly class PermissionList
{
    public function __construct(
        private PermissionTexts $permissionTexts,
        private Translator $translator,
    ) {}

    /**
     * A search matches the label or description in the dashboard's language, or the key, ignoring case, anywhere in the text.
     *
     * @return list<DescribedPermission>
     */
    public function of(GrantablePermissions $grantablePermissions, ?string $search): array
    {
        $permissions = array_map(
            fn (GrantablePermission $permission): DescribedPermission => $this->permissionTexts->describe($permission),
            $grantablePermissions->permissions(),
        );

        if ($search !== null && $search !== '') {
            $permissions = array_values(array_filter($permissions, static fn (DescribedPermission $permission): bool => self::matches($permission, $search)));
        }

        $collator = new Collator($this->translator->getLocale());
        usort($permissions, static fn (DescribedPermission $first, DescribedPermission $second): int => self::compare($collator, $first->groupLabel, $second->groupLabel)
            ?: self::compare($collator, $first->label, $second->label));

        return $permissions;
    }

    private static function matches(DescribedPermission $permission, string $search): bool
    {
        $needle = mb_strtolower($search);

        foreach ([$permission->name, $permission->label, $permission->description] as $text) {
            if (str_contains(mb_strtolower($text), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compares two texts in the language's own alphabetical order (é next to e, for example).
     *
     * @throws LogicException When the language's ordering can't compare them.
     */
    private static function compare(Collator $collator, string $first, string $second): int
    {
        $order = $collator->compare($first, $second);

        if ($order === false) {
            throw new LogicException('The texts could not be compared in this language: '.$collator->getErrorMessage());
        }

        return $order;
    }
}
