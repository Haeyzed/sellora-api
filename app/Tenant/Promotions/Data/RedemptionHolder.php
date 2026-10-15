<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

/**
 * What holds a promotion use: a checkout while it is open, then the order it becomes. As Inventory's stock holders.
 */
final readonly class RedemptionHolder
{
    /**
     * @param  string  $type  Such as "checkout" or "order".
     * @param  string  $publicId  The holder's public ID.
     */
    public function __construct(
        public string $type,
        public string $publicId,
    ) {}
}
