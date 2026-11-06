<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Storefront\Models\Page;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Deletes one of a page's images, with its file. A body still showing it shows a broken image until staff change it.
 */
final readonly class RemovePageImage
{
    /**
     * @param  string  $imageId  The image's ID, as the upload returned it.
     *
     * @throws ModelNotFoundException When the page has no such image.
     */
    public function handle(Page $page, string $imageId): void
    {
        $page->media()->where('collection_name', Page::IMAGES)->where('uuid', $imageId)->firstOrFail()->delete();
    }
}
