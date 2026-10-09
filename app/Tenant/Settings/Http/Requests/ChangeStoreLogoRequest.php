<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Requests;

use App\Shared\Media\StorefrontImage;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Setting or removing the store's logo, which needs the settings.manage permission.
 */
final class ChangeStoreLogoRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('update', StoreSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isMethod('DELETE')) {
            return [];
        }

        return [
            /** A JPEG, PNG or WebP image (never SVG), up to 10 MB and 6000 × 6000 pixels, sent as multipart form data. */
            'logo' => StorefrontImage::rules(),
        ];
    }

    public function logo(): UploadedFile
    {
        $logo = $this->file('logo');

        return $logo instanceof UploadedFile ? $logo : throw new LogicException('The logo was validated as a single file.');
    }
}
