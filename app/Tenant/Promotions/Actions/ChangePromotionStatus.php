<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Services\PromotionActivity;
use Illuminate\Support\Facades\DB;

/**
 * Staff activate, deactivate or archive a promotion. Audited and logged.
 *
 * Deactivating stops new baskets getting it; checkouts that already reserved
 * a use keep it. Archiving is for good: it never applies again, and it stays
 * for the orders that used it.
 */
final readonly class ChangePromotionStatus
{
    public function __construct(private PromotionActivity $promotionActivity) {}

    public function activate(StaffMember $actor, Promotion $promotion): Promotion
    {
        return $this->setActive($actor, $promotion, true);
    }

    public function deactivate(StaffMember $actor, Promotion $promotion): Promotion
    {
        return $this->setActive($actor, $promotion, false);
    }

    public function archive(StaffMember $actor, Promotion $promotion): void
    {
        DB::transaction(function () use ($actor, $promotion): void {
            $promotion->delete();
            $this->promotionActivity->record($actor, $promotion, 'promotion_archived', 'Archived');
        });
    }

    private function setActive(StaffMember $actor, Promotion $promotion, bool $isActive): Promotion
    {
        return DB::transaction(function () use ($actor, $promotion, $isActive): Promotion {
            if ($promotion->is_active !== $isActive) {
                $promotion->is_active = $isActive;
                $promotion->save();
                $this->promotionActivity->record($actor, $promotion, $isActive ? 'promotion_activated' : 'promotion_deactivated', $isActive ? 'Activated' : 'Deactivated');
            }

            return $promotion;
        });
    }
}
