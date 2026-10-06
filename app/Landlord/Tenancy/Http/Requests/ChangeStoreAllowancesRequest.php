<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Revoking a feature grant or removing a limit override, with nothing more to send.
 */
final class ChangeStoreAllowancesRequest extends FormRequest
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
        return 'grant';
    }
}
