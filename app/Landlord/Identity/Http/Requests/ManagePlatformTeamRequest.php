<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Viewing or changing one member, invitation or role of the platform team, with nothing more to send. Super admins only.
 */
final class ManagePlatformTeamRequest extends FormRequest
{
    use ActsAsSuperAdmin;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
