<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;

/**
 * Who to invite to Sellora's team, and the platform roles they will have.
 */
final class InvitePlatformAdminRequest extends FormRequest
{
    use ActsAsSuperAdmin;
    use ChoosesPlatformRoles;

    /**
     * @return array<string, list<string|Exists>>
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
