<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use App\Landlord\Identity\Enums\PlatformPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * The complete new set of permissions a platform admin holds directly, on top of their roles. Super admins only.
 */
final class ChangePlatformAdminPermissionsRequest extends FormRequest
{
    use ActsAsSuperAdmin;

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            /**
             * Permission names, such as ["stores.export"]; an empty list removes every direct permission. See GET /platform/team/permissions.
             *
             * @var list<string>
             */
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(PlatformPermission::class)],
        ];
    }

    /**
     * @return list<PlatformPermission>
     */
    public function platformPermissions(): array
    {
        return array_values(array_map(
            static fn (string $name): PlatformPermission => PlatformPermission::from($name),
            $this->array('permissions'),
        ));
    }
}
