<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Answering a driver's data-protection request: seeing everything held about them, or erasing it (with the staff member's own password). Needs the drivers.manage permission.
 */
final class DriverPrivacyRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Driver::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        if (! $this->isMethod('POST')) {
            return [];
        }

        return [
            /** Your own password, to confirm. */
            'password' => ['required', 'string'],
        ];
    }

    public function actorPassword(): string
    {
        return $this->string('password')->value();
    }
}
