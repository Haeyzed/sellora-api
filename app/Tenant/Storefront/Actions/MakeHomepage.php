<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\MissingStorefrontSettingsException;
use App\Tenant\Storefront\Models\Page;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Support\Facades\DB;

/**
 * Makes a page the one the storefront opens on, in place of the page that was. Shoppers see it once it is published.
 *
 * Changes of homepage queue on the storefront settings row's lock, so two
 * at once leave exactly one homepage: the last one.
 */
final readonly class MakeHomepage
{
    /**
     * @throws MissingStorefrontSettingsException
     */
    public function handle(Page $page): Page
    {
        DB::transaction(static function () use ($page): void {
            StorefrontSettings::query()->whereKey(1)->lockForUpdate()->first() ?? throw new MissingStorefrontSettingsException;

            Page::query()->where('is_homepage', true)->whereKeyNot($page->id)->update(['is_homepage' => false]);
            $page->forceFill(['is_homepage' => true])->save();
        });

        StorefrontContentChanged::dispatch();

        return $page;
    }
}
