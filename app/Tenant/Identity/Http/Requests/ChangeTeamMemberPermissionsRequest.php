<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffPermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use LogicException;

/**
 * The complete new set of permissions a team member holds directly, on top of their roles.
 */
final class ChangeTeamMemberPermissionsRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', $this->staffMember());
    }

    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        return [
            /**
             * Permission names, such as ["orders.refund"]; an empty list removes every direct permission. See GET /staff/team/permissions.
             *
             * @var list<string>
             */
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(app(StaffPermissionCatalogue::class)->all())],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        /** @var list<string> $names */
        $names = array_values($this->array('permissions'));

        return $names;
    }

    /**
     * @throws LogicException When the route has no {staffMember} parameter, which is a routing mistake.
     */
    public function staffMember(): StaffMember
    {
        $staffMember = $this->route('staffMember');

        return $staffMember instanceof StaffMember ? $staffMember : throw new LogicException('This route needs a {staffMember} parameter.');
    }
}
