<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Media\StorefrontImageUploader;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sets the store's logo, replacing the one it had.
 */
final readonly class ChangeStoreLogo
{
    public function __construct(
        private FindStoreSettings $findStoreSettings,
        private StorefrontImageUploader $storefrontImageUploader,
    ) {}

    /**
     * @throws UsageLimitReachedException When the image would take the store over its plan's storage.
     */
    public function handle(UploadedFile $logo): Media
    {
        return $this->storefrontImageUploader->add($this->findStoreSettings->handle(), $logo, StoreSettings::LOGO);
    }
}
