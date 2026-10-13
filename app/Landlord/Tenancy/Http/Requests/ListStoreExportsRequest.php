<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use App\Shared\Auth\AccountReference;
use App\Shared\Http\PageListRequest;

/**
 * A page of your exports of one store. Needs the stores.export permission.
 */
final class ListStoreExportsRequest extends PageListRequest
{
    use StoreAbilityRequest;

    public function requester(): AccountReference
    {
        return AccountReference::to($this->actor());
    }

    protected function ability(): string
    {
        return 'export';
    }
}
