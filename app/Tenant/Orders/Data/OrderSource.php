<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

/**
 * Where an order comes from: a checkout, or a return it replaces in an exchange (Step 18c). Always exactly one.
 *
 * Placing from the same source again returns the order already placed from
 * it: the same checkout, or a return whose replacement isn't cancelled.
 */
final readonly class OrderSource
{
    private function __construct(
        public ?string $checkoutId,
        public ?int $replacesReturnId,
        private string $lockKey,
    ) {}

    /**
     * @param  string  $checkoutId  The checkout's public ID.
     */
    public static function checkout(string $checkoutId): self
    {
        return new self($checkoutId, null, 'order-checkout:'.$checkoutId);
    }

    /**
     * @param  int  $returnId  The return's ID.
     * @param  string  $returnPublicId  The return's public ID, which names its lock.
     */
    public static function replacingReturn(int $returnId, string $returnPublicId): self
    {
        return new self(null, $returnId, 'order-replacement:'.$returnPublicId);
    }

    /**
     * The advisory lock two placements from the same source take, so the second waits and then finds the first's order.
     */
    public function lockKey(): string
    {
        return $this->lockKey;
    }
}
