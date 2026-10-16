<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

use App\Tenant\Orders\Models\Order;

/**
 * The order a checkout became, and whether this call placed it or found it already placed.
 */
final readonly class PlacedOrder
{
    /**
     * @param  bool  $isNew  False when the checkout had already been placed: the same order comes back.
     * @param  string|null  $guestAccessToken  A guest's token for the order-status page, shown once (Step 16 issues it); null until then.
     */
    public function __construct(
        public Order $order,
        public bool $isNew,
        public ?string $guestAccessToken = null,
    ) {}

    public function withGuestAccessToken(string $token): self
    {
        return new self($this->order, $this->isNew, $token);
    }
}
