<?php

declare(strict_types=1);

namespace App\Shared\Media\Concerns;

use App\Shared\Media\StorefrontImage;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * For models with storefront images: the resized copies every such image gets, in WebP, made on the queue.
 *
 * The model declares its own collections (a gallery, a logo) on the public
 * disk, StorefrontImage::DISK.
 */
trait HasStorefrontImages
{
    use InteractsWithMedia;

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(StorefrontImage::THUMBNAIL)->fit(Fit::Max, 400, 400)->format('webp');
        $this->addMediaConversion(StorefrontImage::LARGE)->fit(Fit::Max, 1600, 1600)->format('webp');
    }
}
