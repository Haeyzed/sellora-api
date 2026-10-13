<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;

/**
 * A page of the currencies the store sells in besides its base currency. Needs the pricing.view permission.
 */
final class ListStoreCurrenciesRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', TaxClass::class);
    }
}
