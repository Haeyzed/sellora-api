<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Data;

use App\Tenant\Orders\Models\OrderLine;

/**
 * What can still be returned of one order line, and which units need staff to say why.
 *
 * A line that ships counts its units in shipments: delivered (and of those,
 * delivered within the store's window) or shipped and never marked delivered.
 * A line that doesn't ship (a service) has none: only staff can return it, up
 * to what was bought. Units already in a return that isn't rejected or
 * cancelled, or given back by a refund of lines, are used up either way.
 */
final readonly class ReturnableLine
{
    public function __construct(
        public OrderLine $line,
        public int $delivered,
        public int $deliveredInWindow,
        public int $shippedNotDelivered,
        public int $used,
        public bool $windowApplies,
    ) {}

    /**
     * What a customer or guest can return: delivered units within the window, less what is used up.
     */
    public function leftForBuyer(): int
    {
        if (! $this->line->requires_shipping) {
            return 0;
        }

        return max(0, min($this->deliveredInWindow, $this->delivered - $this->used));
    }

    /**
     * What staff can return: every delivered or shipped unit (or, for a line that doesn't ship, every unit bought), less what is used up.
     */
    public function leftForStaff(): int
    {
        $units = $this->line->requires_shipping ? $this->delivered + $this->shippedNotDelivered : $this->line->quantity;

        return max(0, $units - $this->used);
    }

    /**
     * Whether staff returning this many need to say why they go past the window: some of the units were delivered before it.
     */
    public function needsOutsideWindowReason(int $quantity): bool
    {
        if (! $this->line->requires_shipping || ! $this->windowApplies) {
            return false;
        }

        return min($quantity, max(0, $this->delivered - $this->used)) > max(0, min($this->deliveredInWindow, $this->delivered - $this->used));
    }

    /**
     * Whether staff returning this many need to say why they return units shipped but never marked delivered.
     */
    public function needsUndeliveredReason(int $quantity): bool
    {
        return $this->line->requires_shipping && $quantity > max(0, $this->delivered - $this->used);
    }
}
