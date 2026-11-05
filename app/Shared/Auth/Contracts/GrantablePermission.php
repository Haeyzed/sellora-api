<?php

declare(strict_types=1);

namespace App\Shared\Auth\Contracts;

use BackedEnum;

/**
 * A permission a role can grant, as a domain's permission enum defines it: its name (the enum's value), the group dashboards list it under, and whether granting it needs extra care.
 *
 * Its label and description are translations, read through PermissionTexts.
 */
interface GrantablePermission extends BackedEnum
{
    /**
     * The group it is listed under, such as "orders": the domain it belongs to, a key in the permission_groups translations.
     */
    public function group(): string;

    /**
     * Whether it moves money, shows personal data or internal costs, or changes who may do what, so dashboards warn before granting it.
     */
    public function isSensitive(): bool;
}
