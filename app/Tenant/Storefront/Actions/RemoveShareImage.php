<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\MissingStorefrontSettingsException;
use App\Tenant\Storefront\Models\StorefrontSettings;

/**
 * Deletes the storefront's share image, with its file.
 */
final readonly class RemoveShareImage
{
    /**
     * @throws MissingStorefrontSettingsException
     */
    public function handle(): void
    {
        StorefrontSettings::current()->clearMediaCollection(StorefrontSettings::SHARE_IMAGE);
        StorefrontContentChanged::dispatch();
    }
}
