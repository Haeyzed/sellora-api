<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Tenant\Shipping\Models\ShippingMethod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Retires a shipping method for good: shoppers stop being offered it, and it stays as orders chose it. Audited. Archiving twice changes nothing.
 */
final readonly class ArchiveShippingMethod
{
    public function handle(ShippingMethod $method): ShippingMethod
    {
        return DB::transaction(static function () use ($method): ShippingMethod {
            ShippingMethod::query()->whereKey($method->getKey())->lockForUpdate()->firstOrFail();
            $method->refresh();

            if (! $method->isArchived()) {
                $method->forceFill(['archived_at' => CarbonImmutable::now()])->save();
            }

            return $method;
        });
    }
}
