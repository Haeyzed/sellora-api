<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use App\Shared\Auth\AccountReference;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Requesting, checking or downloading a full export of a store, which needs the stores.export permission.
 */
final class ExportStoreRequest extends FormRequest
{
    use StoreAbilityRequest;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function requester(): AccountReference
    {
        return AccountReference::to($this->actor());
    }

    protected function ability(): string
    {
        return 'export';
    }
}
