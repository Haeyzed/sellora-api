<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Data;

use InvalidArgumentException;

/**
 * The value of a store's limit override: either a number, or unlimited stated on purpose.
 *
 * There is no way to build one from a missing value, so a bug can never
 * turn "nothing was sent" into "unlimited" (section 8).
 */
final readonly class LimitOverrideValue
{
    private function __construct(
        public bool $isUnlimited,
        public ?int $limit,
    ) {}

    public static function unlimited(): self
    {
        return new self(isUnlimited: true, limit: null);
    }

    /**
     * @throws InvalidArgumentException When the limit is negative.
     */
    public static function of(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('A usage limit cannot be negative.');
        }

        return new self(isUnlimited: false, limit: $limit);
    }
}
