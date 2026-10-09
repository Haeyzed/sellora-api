<?php

declare(strict_types=1);

namespace App\Shared\Media\Http\Resources;

use App\Shared\Media\StorefrontImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A storefront image: the original's address, and its resized copies' once they are made.
 *
 * @property Media $resource
 */
final class StorefrontImageResource extends JsonResource
{
    public function __construct(Media $media)
    {
        parent::__construct($media);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->resource;

        return [
            'id' => $media->uuid,
            /** The uploaded image as it was sent. */
            'url' => $media->getUrl(),
            /** At most 400 pixels across, in WebP; null until it has been made. */
            'thumbnail_url' => $media->hasGeneratedConversion(StorefrontImage::THUMBNAIL) ? $media->getUrl(StorefrontImage::THUMBNAIL) : null,
            /** At most 1600 pixels across, in WebP; null until it has been made. */
            'large_url' => $media->hasGeneratedConversion(StorefrontImage::LARGE) ? $media->getUrl(StorefrontImage::LARGE) : null,
            'width' => $media->getCustomProperty('width'),
            'height' => $media->getCustomProperty('height'),
        ];
    }
}
