<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Shared\Http\CursorListRequest;

/**
 * A page of one part of the storefront's sitemap. Open to anyone, such as a search engine's crawler.
 */
final class ListSitemapRequest extends CursorListRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
