<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Models\Banner;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing a banner, which needs the storefront.manage permission.
 */
final class UpdateBannerRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesBannerDetails;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Banner::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->bannerRules(creating: false);
    }
}
