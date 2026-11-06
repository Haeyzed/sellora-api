<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Media\StorefrontImageUploader;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Uploads an image for a page's body. Its address is the only kind of image a page body may show: the store's own.
 */
final readonly class AddPageImage
{
    public function __construct(private StorefrontImageUploader $storefrontImageUploader) {}

    /**
     * @throws UsageLimitReachedException When the image would take the store over its plan's storage.
     */
    public function handle(Page $page, UploadedFile $image): Media
    {
        return $this->storefrontImageUploader->add($page, $image, Page::IMAGES);
    }
}
