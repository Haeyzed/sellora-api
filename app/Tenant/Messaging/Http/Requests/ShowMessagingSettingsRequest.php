<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Messaging\Models\MessagingSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Seeing the messaging settings, which needs the settings.view permission.
 */
final class ShowMessagingSettingsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('view', MessagingSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
