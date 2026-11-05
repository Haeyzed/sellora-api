<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A change to the storefront's content that sends no data, such as removing an image, which needs the storefront.manage permission.
 */
final class ManageStorefrontRequest extends FormRequest
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
        return [];
    }
}
