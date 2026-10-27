<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;

/**
 * A page of the store's drivers. Needs the delivery.view permission.
 */
final class ListDriversRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Driver::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Only active drivers (true) or only deactivated ones (false); both when left out. */
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function activeFilter(): ?bool
    {
        return $this->has('active') ? $this->boolean('active') : null;
    }
}
