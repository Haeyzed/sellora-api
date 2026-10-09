<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Actions;

use App\Tenant\Settings\Models\StoreSettings;

/**
 * Deletes the store's logo, with its file.
 */
final readonly class RemoveStoreLogo
{
    public function __construct(private FindStoreSettings $findStoreSettings) {}

    public function handle(): void
    {
        $this->findStoreSettings->handle()->clearMediaCollection(StoreSettings::LOGO);
    }
}
