<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Deactivating or reactivating a member of the store's team.
 */
final class ManageTeamMemberRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', $this->staffMember());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
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
