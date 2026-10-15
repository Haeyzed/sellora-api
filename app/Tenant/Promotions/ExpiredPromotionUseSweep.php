<?php

declare(strict_types=1);

namespace App\Tenant\Promotions;

use App\Shared\Tenancy\Contracts\StorePromotionHolds;
use App\Tenant\Promotions\Actions\ReleaseExpiredPromotionUses;

/**
 * The current store's part of the scheduled sweep of expired holds: promotion uses reserved by checkouts that ran out.
 */
final readonly class ExpiredPromotionUseSweep implements StorePromotionHolds
{
    public function __construct(private ReleaseExpiredPromotionUses $releaseExpiredPromotionUses) {}

    public function releaseExpired(): int
    {
        return $this->releaseExpiredPromotionUses->handle(config()->integer('inventory.expired_reservations_per_run'));
    }
}
