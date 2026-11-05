<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Media\StorefrontImageUploader;
use App\Tenant\Storefront\Events\StorefrontContentChanged;
use App\Tenant\Storefront\Exceptions\MissingStorefrontSettingsException;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sets the image social sites show when a storefront page is shared, replacing the one it had.
 */
final readonly class ChangeShareImage
{
    public function __construct(private StorefrontImageUploader $storefrontImageUploader) {}

    /**
     * @throws UsageLimitReachedException When the image would take the store over its plan's storage.
     * @throws MissingStorefrontSettingsException
     */
    public function handle(UploadedFile $image): Media
    {
        $media = $this->storefrontImageUploader->add(StorefrontSettings::current(), $image, StorefrontSettings::SHARE_IMAGE);
        StorefrontContentChanged::dispatch();

        return $media;
    }
}
