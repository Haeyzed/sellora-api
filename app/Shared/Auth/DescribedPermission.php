<?php

declare(strict_types=1);

namespace App\Shared\Auth;

/**
 * A permission as people see it, in one language: its short label, what it allows, its group's name, and whether granting it needs extra care.
 */
final readonly class DescribedPermission
{
    /**
     * @param  string  $name  The permission's key, such as "orders.view": a code identifier, never shown to people.
     * @param  string  $groupKey  The group it is listed under, such as "orders".
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $description,
        public string $groupKey,
        public string $groupLabel,
        public bool $isSensitive,
    ) {}
}
