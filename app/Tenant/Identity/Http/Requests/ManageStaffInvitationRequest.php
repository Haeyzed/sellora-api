<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Models\StaffInvitation;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Resending or cancelling one invitation.
 */
final class ManageStaffInvitationRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can($this->isMethod('DELETE') ? 'delete' : 'update', $this->staffInvitation());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @throws LogicException When the route has no {staffInvitation} parameter, which is a routing mistake.
     */
    public function staffInvitation(): StaffInvitation
    {
        $staffInvitation = $this->route('staffInvitation');

        return $staffInvitation instanceof StaffInvitation ? $staffInvitation : throw new LogicException('This route needs a {staffInvitation} parameter.');
    }
}
