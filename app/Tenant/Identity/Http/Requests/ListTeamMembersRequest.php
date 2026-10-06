<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Identity\Models\StaffMember;

/**
 * A page of the store's team, optionally only active or only deactivated members.
 */
final class ListTeamMembersRequest extends PaginatedListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', StaffMember::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Only active (true) or only deactivated (false) members; both when left out. */
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function activeFilter(): ?bool
    {
        return $this->has('is_active') ? $this->boolean('is_active') : null;
    }
}
