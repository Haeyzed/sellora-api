<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Http\Requests\ListSeveralStorefrontProductsRequest;
use App\Tenant\Catalog\Http\Requests\ListStorefrontProductLinksRequest;
use App\Tenant\Catalog\Http\Requests\ListStorefrontProductsRequest;
use App\Tenant\Catalog\Http\Requests\ShowStorefrontProductRequest;
use App\Tenant\Catalog\Http\Resources\StorefrontProductList;
use App\Tenant\Catalog\Http\Resources\StorefrontProductResource;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductLink;
use App\Tenant\Catalog\Services\SimilarProducts;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The products customers can buy, for the storefront. Open to anyone, rate-limited per visitor.
 *
 * Only published products with at least one priced variant are shown, and
 * only their priced variants (section 3.3). Texts come in the language the
 * client asks for (Accept-Language) when the store publishes in it, and
 * otherwise in the store's default language. Prices come in the currency the
 * client asks for (?currency=) while the store offers it, otherwise in the
 * store's own currency.
 */
final class StorefrontProductController extends Controller
{
    public function __construct(private readonly StorefrontProductList $storefrontProductList) {}

    /**
     * List products.
     *
     * Newest first. Search the names in any of the store's languages, SKUs,
     * barcodes and brand names, or narrow to a category or brand. Prices come
     * in the currency asked for (?currency=USD) while the store offers it;
     * otherwise, including while its exchange rate is out of date, in the
     * store's own currency. Every price says which currency it is in.
     */
    public function index(ListStorefrontProductsRequest $request): AnonymousResourceCollection
    {
        $query = Product::query()->visibleToCustomers()->with(StorefrontProductResource::relations());
        $request->applyFilters($query);

        $page = $query->orderByDesc('id')->cursorPaginate($request->perPage());
        $resources = $this->storefrontProductList->resources($page->items(), $request->requestedCurrency());

        return StorefrontProductResource::collection($page->setCollection(collect($resources)));
    }

    /**
     * Show a product.
     *
     * Found by its slug, such as "linen-shirt". A product customers can't buy
     * is not found. Prices come in the currency asked for (?currency=USD) while
     * the store offers it, otherwise in the store's own currency.
     */
    public function show(ShowStorefrontProductRequest $request, string $slug): StorefrontProductResource
    {
        $product = Product::query()->visibleToCustomers()->where('slug', $slug)->with(StorefrontProductResource::relations())->firstOrFail();

        return $this->storefrontProductList->resources([$product], $request->requestedCurrency())[0];
    }

    /**
     * Fetch several products.
     *
     * The products with the IDs asked for (?ids[]=...), in that order, for
     * comparing products and for the shopper's recently viewed list, which
     * the storefront keeps in the browser: nothing is stored. A product that
     * doesn't exist, or that customers can't buy, is left out without an
     * error, so a list kept in a browser never breaks when a product goes.
     * Not paged: at most catalog.max_products_fetched IDs (50) are asked for.
     */
    public function several(ListSeveralStorefrontProductsRequest $request): AnonymousResourceCollection
    {
        $ids = $request->productIds();
        $products = Product::query()->visibleToCustomers()->whereIn('public_id', $ids)->with(StorefrontProductResource::relations())->get()->keyBy('public_id');
        $inOrderAsked = array_values(array_filter(array_map(static fn (string $id): ?Product => $products->get($id), $ids)));

        return StorefrontProductResource::collection($this->storefrontProductList->resources($inOrderAsked, $request->requestedCurrency()));
    }

    /**
     * List a product's links.
     *
     * The products the merchant picked to show beside this one, of one kind
     * (?type=related, upsell or cross_sell), in the merchant's order. Only
     * products customers can buy are shown. The product itself is found by
     * its slug; one customers can't buy is not found. Not paged: a product
     * has at most catalog.max_links_per_type links of each kind (20).
     */
    public function links(ListStorefrontProductLinksRequest $request, string $slug): AnonymousResourceCollection
    {
        $product = Product::query()->visibleToCustomers()->where('slug', $slug)->firstOrFail();
        $linkedIds = ProductLink::query()->where('product_id', $product->id)->where('type', $request->linkType())->orderBy('position')->pluck('linked_product_id')->all();
        $linked = Product::query()->visibleToCustomers()->whereKey($linkedIds)->with(StorefrontProductResource::relations())->get()->keyBy('id');
        $inMerchantOrder = array_values(array_filter(array_map(static fn (int $id): ?Product => $linked->get($id), $linkedIds)));

        return StorefrontProductResource::collection($this->storefrontProductList->resources($inMerchantOrder, $request->requestedCurrency()));
    }

    /**
     * List similar products.
     *
     * Suggestions to show beside a product, found by its slug: first the
     * products the merchant linked as related, then others in the same
     * category, then of the same brand, then at a similar price (within
     * catalog.similar_products.price_band_percent, 25%, of its lowest price).
     * Never the product itself, and only products that can be bought now.
     * Not paged: at most catalog.similar_products.max_shown (12). The list may
     * be up to a few minutes old; prices and availability are always current.
     */
    public function similar(ShowStorefrontProductRequest $request, string $slug, SimilarProducts $similarProducts): AnonymousResourceCollection
    {
        $product = Product::query()->visibleToCustomers()->where('slug', $slug)->firstOrFail();
        $candidateIds = $similarProducts->candidateIdsFor($product);
        $candidates = Product::query()->visibleToCustomers()->whereKey($candidateIds)->with(StorefrontProductResource::relations())->get()->keyBy('id');
        $inOrder = array_values(array_filter(array_map(static fn (int $id): ?Product => $candidates->get($id), $candidateIds)));

        return StorefrontProductResource::collection($this->storefrontProductList->buyableNow($inOrder, config()->integer('catalog.similar_products.max_shown'), $request->requestedCurrency()));
    }
}
