<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Http\Requests\ListStorefrontProductsRequest;
use App\Tenant\Catalog\Http\Requests\ShowStorefrontProductRequest;
use App\Tenant\Catalog\Http\Resources\StorefrontProductResource;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Inventory\Actions\FindVariantAvailability;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Data\VariantPrice;
use App\Tenant\Pricing\VariantPricer;
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
    public function __construct(
        private readonly FindVariantAvailability $findVariantAvailability,
        private readonly FindExchangeRates $findExchangeRates,
        private readonly VariantPricer $variantPricer,
    ) {}

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

        $availability = $this->findVariantAvailability->handle($this->variantIds($page->items()));
        $prices = $this->pricesOf($page->items(), $request->requestedCurrency());

        return StorefrontProductResource::collection($page->through(static fn (Product $product): StorefrontProductResource => new StorefrontProductResource($product, $availability, $prices)));
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

        return new StorefrontProductResource($product, $this->findVariantAvailability->handle($this->variantIds([$product])), $this->pricesOf([$product], $request->requestedCurrency()));
    }

    /**
     * What the products' variants cost in the currency asked for, or the store's own: a fixed number of queries for the whole page.
     *
     * @param  array<int, Product>  $products
     * @return array<int, VariantPrice> By variant ID.
     */
    private function pricesOf(array $products, ?string $requestedCurrency): array
    {
        $variants = [];

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $variants[] = $variant;
            }
        }

        return $this->variantPricer->priceAt($variants, $this->findExchangeRates->forStorefront($requestedCurrency));
    }

    /**
     * The IDs of the variants shown for these products.
     *
     * @param  array<int, Product>  $products
     * @return list<int>
     */
    private function variantIds(array $products): array
    {
        $ids = [];
        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $ids[] = $variant->id;
            }
        }

        return $ids;
    }
}
