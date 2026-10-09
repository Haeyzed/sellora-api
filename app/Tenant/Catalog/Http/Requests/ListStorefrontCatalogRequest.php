<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Shared\Http\PaginatedListRequest;

/**
 * A page of the store's categories or brands, as customers see them. Anyone may ask.
 */
final class ListStorefrontCatalogRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
