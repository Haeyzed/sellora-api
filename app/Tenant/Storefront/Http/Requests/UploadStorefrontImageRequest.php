<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Shared\Media\StorefrontImage;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Uploading one of the storefront's images (the share image, a page's image or a banner's), which needs the storefront.manage permission.
 */
final class UploadStorefrontImageRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', StorefrontSettings::class);
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
