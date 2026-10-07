<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Requests;

use App\Shared\Auth\AccountReference;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The owner requesting, checking or downloading a full export of their store.
 */
final class ExportOwnStoreRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('exportStore');
    }

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
}
