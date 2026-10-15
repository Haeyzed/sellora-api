<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a zone and the regions it covers, which then get no shipping until another zone takes them. Audited.
 */
final readonly class DeleteShippingZone
{
    public function handle(ShippingZone $zone): void
    {
        DB::transaction(static function () use ($zone): void {
            ShippingZone::query()->whereKey($zone->getKey())->lockForUpdate()->firstOrFail()->delete();
        });
    }
}
