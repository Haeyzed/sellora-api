<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Services;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Models\Promotion;

/**
 * Writes what staff did to promotions in the store's activity log ("promotions"), naming them by public ID and listing which fields changed.
 */
final readonly class PromotionActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(StaffMember $actor, Promotion $promotion, string $event, string $description, array $properties = []): void
    {
        $entry = activity('promotions')
            ->causedBy($actor)
            ->performedOn($promotion)
            ->event($event);

        if ($properties !== []) {
            $entry->withProperties($properties);
        }

        $entry->log("{$description} promotion {$promotion->public_id}");
    }
}
