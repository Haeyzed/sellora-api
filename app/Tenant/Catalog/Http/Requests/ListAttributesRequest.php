<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;

/**
 * A page of the store's attributes, with their values.
 */
final class ListAttributesRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Attribute::class);
    }
}
