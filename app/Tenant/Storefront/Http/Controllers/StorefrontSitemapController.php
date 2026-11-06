<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Storefront\Enums\SitemapType;
use App\Tenant\Storefront\Http\Requests\ListSitemapRequest;
use App\Tenant\Storefront\Http\Resources\SitemapEntryResource;
use App\Tenant\Storefront\Models\Page;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The storefront's sitemap, for search engines. Open to anyone, rate-limited per visitor.
 */
final class StorefrontSitemapController extends Controller
{
    /**
     * List one part of the sitemap.
     *
     * "pages", "products", "categories" or "brands": only what shoppers may
     * see (published pages, products they can buy, categories and brands
     * outside the trash), each with its slug and when it last changed. Paged
     * by cursor; the storefront writes the sitemap's XML from it.
     */
    public function show(ListSitemapRequest $request, SitemapType $type): AnonymousResourceCollection
    {
        $query = match ($type) {
            SitemapType::Pages => Page::query()->published(),
            SitemapType::Products => Product::query()->visibleToCustomers(),
            SitemapType::Categories => Category::query(),
            SitemapType::Brands => Brand::query(),
        };

        return SitemapEntryResource::collection($query->orderBy('id')->cursorPaginate($request->perPage(), ['id', 'slug', 'updated_at']));
    }
}
