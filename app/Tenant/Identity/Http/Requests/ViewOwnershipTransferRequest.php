<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Looking at the store's pending ownership transfer. Only the two people it concerns find it; everyone else gets 404.
 */
final class ViewOwnershipTransferRequest extends FormRequest
{
    use ActsAsStaffMember;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
