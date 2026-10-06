<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Managing the platform team (admins, invitations, roles, two-factor resets) is for super admins only.
 *
 * @mixin FormRequest
 */
trait ActsAsSuperAdmin
{
    use ActsAsPlatformAdmin;

    public function authorize(): bool
    {
        return $this->actor()->isSuperAdmin();
    }
}
