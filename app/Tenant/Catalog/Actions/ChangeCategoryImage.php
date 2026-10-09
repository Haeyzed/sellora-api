<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Media\StorefrontImageUploader;
use App\Tenant\Catalog\Models\Category;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sets a category's image, replacing the one it had.
 */
final readonly class ChangeCategoryImage
{
    public function __construct(private StorefrontImageUploader $storefrontImageUploader) {}

    /**
     * @throws UsageLimitReachedException When the image would take the store over its plan's storage.
     */
    public function handle(Category $category, UploadedFile $image): Media
    {
        return $this->storefrontImageUploader->add($category, $image, Category::IMAGE);
    }
}
