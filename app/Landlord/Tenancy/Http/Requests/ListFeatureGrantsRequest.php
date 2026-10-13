<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use App\Shared\Http\PageListRequest;

/**
 * A page of one store's feature grants, active and ended. Needs the stores.view permission.
 */
final class ListFeatureGrantsRequest extends PageListRequest
{
    use StoreAbilityRequest;

    protected function ability(): string
    {
        return 'view';
    }
}
