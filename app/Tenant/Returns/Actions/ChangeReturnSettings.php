<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Tenant\Returns\Exceptions\MissingReturnSettingsException;
use App\Tenant\Returns\Models\ReturnSettings;
use Illuminate\Support\Facades\DB;

/**
 * Changes how the store takes returns: the window, whether buyers may ask themselves, and whether a photo is required for damage or the wrong item. What is left out stays as it is.
 *
 * The row is changed under its lock, so two changes at once both land, one
 * after the other. It always exists: it is created with the store.
 */
final readonly class ChangeReturnSettings
{
    /**
     * @throws MissingReturnSettingsException When the store has no return settings: a bug, never filled in with defaults.
     */
    public function handle(?int $windowDays = null, ?bool $customerRequests = null, ?bool $photosRequired = null): ReturnSettings
    {
        return DB::transaction(static function () use ($windowDays, $customerRequests, $photosRequired): ReturnSettings {
            $settings = ReturnSettings::query()->whereKey(1)->lockForUpdate()->first() ?? throw new MissingReturnSettingsException;
            $settings->forceFill(array_filter([
                'window_days' => $windowDays,
                'customer_requests' => $customerRequests,
                'photos_required' => $photosRequired,
            ], static fn (mixed $value): bool => $value !== null))->save();

            return $settings;
        });
    }
}
