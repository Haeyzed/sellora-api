<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Tenant\Shipping\Exceptions\ShippingZoneHasMethodsException;
use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a zone and the regions it covers, which then get no shipping until another zone takes them. Audited.
 *
 * Only once every method in it is archived; archived methods outlive it,
 * zoneless, as orders chose them. The zone stays locked while it is checked,
 * so a method added to it at the same moment waits and then fails.
 */
final readonly class DeleteShippingZone
{
    /**
     * @throws ShippingZoneHasMethodsException When a method in it isn't archived.
     */
    public function handle(ShippingZone $zone): void
    {
        DB::transaction(static function () use ($zone): void {
            $locked = ShippingZone::query()->whereKey($zone->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->methods()->whereNull('archived_at')->exists()) {
                throw new ShippingZoneHasMethodsException;
            }

            $locked->delete();
        });
    }
}
