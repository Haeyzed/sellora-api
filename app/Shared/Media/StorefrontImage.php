<?php

declare(strict_types=1);

namespace App\Shared\Media;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * The rules every storefront image upload follows (section 14): JPEG, PNG or WebP, never SVG, within a file size and maximum dimensions from config/media.php.
 *
 * Dimensions are read from the file's header only, so an image built to
 * decode into something enormous is refused before it is ever decoded.
 */
final class StorefrontImage
{
    /** A small square-ish copy for lists, at most 400 pixels across. */
    public const string THUMBNAIL = 'thumbnail';

    /** A copy for product pages, at most 1600 pixels across. */
    public const string LARGE = 'large';

    /**
     * The public disk storefront images go on (config media.storefront_images.disk): "public", served on the store's own domain, until S3 behind a CDN is set up. Every other upload stays private.
     */
    public static function disk(): string
    {
        return config()->string('media.storefront_images.disk');
    }

    /**
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return [
            'required',
            File::image()
                ->types(config()->array('media.storefront_images.types'))
                ->max(config()->integer('media.storefront_images.max_kilobytes')),
            Rule::dimensions()
                ->maxWidth(config()->integer('media.storefront_images.max_width'))
                ->maxHeight(config()->integer('media.storefront_images.max_height')),
        ];
    }
}
