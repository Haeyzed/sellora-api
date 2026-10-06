<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Seeing one store and its grants and limit overrides.
 */
final class ViewStoreRequest extends FormRequest
{
    use StoreAbilityRequest;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    protected function ability(): string
    {
        return 'view';
    }
}
