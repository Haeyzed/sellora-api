<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Shared\Media\StorefrontImage;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Uploading a catalog image (a product gallery image, a category image or a brand logo), which needs the catalog.manage permission.
 */
final class UploadCatalogImageRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('update', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** A JPEG, PNG or WebP image (never SVG), up to 10 MB and 6000 × 6000 pixels, sent as multipart form data. */
            'image' => StorefrontImage::rules(),
        ];
    }

    public function uploadedImage(): UploadedFile
    {
        $image = $this->file('image');

        return $image instanceof UploadedFile ? $image : throw new LogicException('The image was validated as a single file.');
    }
}
