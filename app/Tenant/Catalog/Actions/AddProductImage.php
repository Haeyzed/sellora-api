<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Media\StorefrontImageUploader;
use App\Tenant\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Adds an image to the end of a product's gallery. The first image of the gallery is the one shown in lists.
 */
final readonly class AddProductImage
{
    public function __construct(private StorefrontImageUploader $storefrontImageUploader) {}

    /**
     * @throws UsageLimitReachedException When the image would take the store over its plan's storage.
     */
    public function handle(Product $product, UploadedFile $image): Media
    {
        return $this->storefrontImageUploader->add($product, $image, Product::GALLERY);
    }
}
