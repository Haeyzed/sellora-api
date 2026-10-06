<?php

declare(strict_types=1);

namespace App\Shared\Retention;

/**
 * Which databases a retention policy cleans: the platform's central database, every store's database, or both.
 */
enum RetentionScope: string
{
    case Central = 'central';
    case Tenant = 'tenant';
    case Both = 'both';

    /**
     * Whether this policy should run in the given context (a store's database, or the central one).
     */
    public function appliesTo(bool $isTenantContext): bool
    {
        return match ($this) {
            self::Both => true,
            self::Tenant => $isTenantContext,
            self::Central => ! $isTenantContext,
        };
    }
}
