<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Why a platform admin is closing a store.
 */
final class CloseStoreRequest extends FormRequest
{
    use StoreAbilityRequest;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** For the platform team, such as "Owner asked by email to close it". */
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->value());
    }

    protected function ability(): string
    {
        return 'manage';
    }
}
