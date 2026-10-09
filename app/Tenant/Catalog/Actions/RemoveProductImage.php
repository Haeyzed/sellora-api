<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Deletes an image from a product's gallery, with its file. A variant that showed it shows none; the next image becomes the first if this one was.
 */
final readonly class RemoveProductImage
{
    public function handle(Media $image): void
    {
        $image->delete();
    }
}
