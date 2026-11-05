<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Seeing the storefront settings, which needs the storefront.view permission.
 */
final class ShowStorefrontSettingsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('view', StorefrontSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
