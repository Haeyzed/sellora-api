<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Looking at one member of the store's team.
 */
final class ViewTeamMemberRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        $staffMember = $this->route('staffMember');

        return $staffMember instanceof StaffMember && $this->actor()->can('view', $staffMember);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
