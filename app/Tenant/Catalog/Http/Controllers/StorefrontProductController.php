<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Http\Requests\ListStorefrontProductsRequest;
use App\Tenant\Catalog\Http\Resources\StorefrontProductResource;
use App\Tenant\Catalog\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The products customers can buy, for the storefront. Open to anyone, rate-limited per visitor.
 *
 * Only published products with at least one priced variant are shown, and
 * only their priced variants (section 3.3). Texts come in the language the
 * client asks for (Accept-Language) when the store publishes in it, and
 * otherwise in the store's default language.
 */
final class StorefrontProductController extends Controller
{
    /**
     * List products.
     *
     * Newest first. Search the names in any of the store's languages, SKUs,
     * barcodes and brand names, or narrow to a category or brand.
     */
    public function index(ListStorefrontProductsRequest $request): AnonymousResourceCollection
    {
        $query = Product::query()->visibleToCustomers()->with(StorefrontProductResource::relations());
        $request->applyFilters($query);

        return StorefrontProductResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show a product.
     *
     * Found by its slug, such as "linen-shirt". A product customers can't buy
     * is not found.
     */
    public function show(string $slug): StorefrontProductResource
    {
        return new StorefrontProductResource(Product::query()->visibleToCustomers()->where('slug', $slug)->with(StorefrontProductResource::relations())->firstOrFail());
    }
}
