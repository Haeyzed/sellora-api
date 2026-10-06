<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Why a store is being suspended.
 */
final class SuspendStoreRequest extends FormRequest
{
    use StoreAbilityRequest;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** For the platform team; never shown to the store's customers. */
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
