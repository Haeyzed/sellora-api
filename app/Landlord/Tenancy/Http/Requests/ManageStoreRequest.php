<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reactivating a store or retrying its setup, with nothing more to send.
 */
final class ManageStoreRequest extends FormRequest
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
        return 'manage';
    }
}
