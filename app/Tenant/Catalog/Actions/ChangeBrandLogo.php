<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Media\StorefrontImageUploader;
use App\Tenant\Catalog\Models\Brand;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sets a brand's logo, replacing the one it had.
 */
final readonly class ChangeBrandLogo
{
    public function __construct(private StorefrontImageUploader $storefrontImageUploader) {}

    /**
     * @throws UsageLimitReachedException When the image would take the store over its plan's storage.
     */
    public function handle(Brand $brand, UploadedFile $logo): Media
    {
        return $this->storefrontImageUploader->add($brand, $logo, Brand::LOGO);
    }
}
