<?php

declare(strict_types=1);

namespace App\Shared\Features;

/**
 * Where a store stands with one module or integration, which decides what its staff and customers may do with it.
 */
enum FeatureState: string
{
    /** On the store's plan and switched on: full access. */
    case Enabled = 'enabled';

    /** Was on the plan but removed by a downgrade: existing data stays visible, nothing new can be created or changed. */
    case Locked = 'locked';

    /** The whole store is suspended, for example for an unpaid subscription: blocked, data kept. */
    case Suspended = 'suspended';

    /** On the plan, but the merchant switched it off: blocked, data kept. */
    case Disabled = 'disabled';

    /** Not on the plan and never used: blocked. */
    case Unavailable = 'unavailable';

    /**
     * Whether existing data may still be read (but not changed) in this state.
     */
    public function allowsReading(): bool
    {
        return $this === self::Enabled || $this === self::Locked;
    }

    /**
     * Whether customers may still finish what they already started (the module's wind-down routes).
     */
    public function allowsWindDown(): bool
    {
        return $this === self::Locked || $this === self::Suspended;
    }

    /**
     * Ranks how strongly a state blocks access, so a feature is never more open than the features it depends on.
     */
    public function restrictiveness(): int
    {
        return match ($this) {
            self::Enabled => 0,
            self::Locked => 1,
            self::Disabled => 2,
            self::Suspended => 3,
            self::Unavailable => 4,
        };
    }
}
