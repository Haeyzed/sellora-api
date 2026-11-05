<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Listing the permissions platform roles can grant, optionally searched by label, description or key. Super admins only.
 */
final class ListPlatformPermissionsRequest extends FormRequest
{
    use ActsAsSuperAdmin;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Text to look for in each permission's label or description (in the dashboard's language) or its key, ignoring case. */
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function search(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) ? $search : null;
    }
}
