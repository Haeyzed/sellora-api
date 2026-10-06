<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;
use LogicException;

/**
 * The complete new set of roles for a member of the store's team.
 */
final class ChangeTeamMemberRolesRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ChoosesStaffRoles;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', $this->staffMember());
    }

    /**
     * @return array<string, list<string|ValidationRule|Exists>>
     */
    public function rules(): array
    {
        return $this->roleRules();
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
