<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Seeing the store's settings, which needs the settings.view permission.
 */
final class ViewStoreSettingsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('view', StoreSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
