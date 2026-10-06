<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Models\StaffInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;

/**
 * Who to invite to the store's team, and the roles they will have.
 */
final class InviteStaffMemberRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ChoosesStaffRoles;

    public function authorize(): bool
    {
        return $this->actor()->can('create', StaffInvitation::class);
    }

    /**
     * @return array<string, list<string|ValidationRule|Exists>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            /** Suggested name; the person can change it when they accept. */
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            ...$this->roleRules(),
        ];
    }

    public function normalisedEmail(): string
    {
        return mb_strtolower(trim($this->string('email')->value()));
    }

    public function suggestedName(): ?string
    {
        return $this->filled('name') ? trim($this->string('name')->value()) : null;
    }
}
