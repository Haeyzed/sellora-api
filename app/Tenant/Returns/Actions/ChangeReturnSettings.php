<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Tenant\Returns\Models\ReturnSettings;
use Illuminate\Support\Facades\DB;

/**
 * Changes how the store takes returns: the window, whether buyers may ask themselves, and whether a photo is required for damage or the wrong item. What is left out stays as it is.
 *
 * The row is made from the defaults the first time, then changed under its
 * lock, so two changes at once both land, one after the other.
 */
final readonly class ChangeReturnSettings
{
    public function handle(?int $windowDays = null, ?bool $customerRequests = null, ?bool $photosRequired = null): ReturnSettings
    {
        return DB::transaction(static function () use ($windowDays, $customerRequests, $photosRequired): ReturnSettings {
            $defaults = ReturnSettings::current();
            ReturnSettings::query()->insertOrIgnore([
                'id' => 1,
                'window_days' => $defaults->window_days,
                'customer_requests' => $defaults->customer_requests,
                'photos_required' => $defaults->photos_required,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $settings = ReturnSettings::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $settings->forceFill(array_filter([
                'window_days' => $windowDays,
                'customer_requests' => $customerRequests,
                'photos_required' => $photosRequired,
            ], static fn (mixed $value): bool => $value !== null))->save();

            return $settings;
        });
    }
}
