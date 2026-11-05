<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Data\StorefrontSettingsChanges;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\MissingStorefrontSettingsException;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Support\Facades\DB;

/**
 * Changes how the storefront presents itself to search engines: its title pattern, its default description and whether search engines may list it. What is left out stays as it is.
 *
 * The row is changed under its lock, so two changes at once both land, one
 * after the other. It always exists: it is created with the store.
 */
final readonly class ChangeStorefrontSettings
{
    /**
     * @throws MissingStorefrontSettingsException When the store has no storefront settings: a bug, never filled in with defaults.
     */
    public function handle(StorefrontSettingsChanges $changes): StorefrontSettings
    {
        return DB::transaction(static function () use ($changes): StorefrontSettings {
            $settings = StorefrontSettings::query()->whereKey(1)->lockForUpdate()->first() ?? throw new MissingStorefrontSettingsException;

            if ($changes->seoTitlePattern !== null) {
                $settings->seo_title_pattern = $changes->seoTitlePattern;
            }

            if ($changes->removesSeoDescription) {
                $settings->seo_description = null;
            } elseif ($changes->seoDescription !== null) {
                $settings->changeTranslations('seo_description', $changes->seoDescription);
            }

            if ($changes->robots !== null) {
                $settings->robots = $changes->robots;
            }

            $settings->save();
            StorefrontContentChanged::dispatch();

            return $settings;
        });
    }
}
