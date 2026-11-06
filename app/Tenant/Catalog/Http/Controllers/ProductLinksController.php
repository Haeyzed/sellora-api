<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\ReplaceProductLinks;
use App\Tenant\Catalog\Enums\ProductLinkType;
use App\Tenant\Catalog\Http\Requests\ReplaceProductLinksRequest;
use App\Tenant\Catalog\Http\Requests\ShowProductLinksRequest;
use App\Tenant\Catalog\Http\Resources\ProductLinksResource;
use App\Tenant\Catalog\Models\Product;

/**
 * The products a merchant picks to show beside a product: related products, upsells and cross-sells.
 */
final class ProductLinksController extends Controller
{
    /**
     * Show a product's links.
     *
     * Needs the catalog.view permission. Every kind, each in the order
     * shoppers see them. A link to a product shoppers can't buy now (a draft,
     * unpriced, or in the trash) is listed here but not shown to shoppers.
     */
    public function show(ShowProductLinksRequest $request, Product $product): ProductLinksResource
    {
        return new ProductLinksResource($product->load(ProductLinksResource::RELATIONS));
    }

    /**
     * Replace a product's links of one kind.
     *
     * Needs the catalog.manage permission. Sends every linked product of
     * this kind (related, upsell or cross_sell), in the order shoppers see
     * them; an empty list removes them all. At most catalog.max_links_per_type
     * (20). A product never links to itself or to a product in the trash. A
     * link may go to a draft or unpriced product: shoppers see it once they
     * can buy that product.
     */
    public function update(ReplaceProductLinksRequest $request, Product $product, ProductLinkType $type, ReplaceProductLinks $replaceProductLinks): ProductLinksResource
    {
        $replaceProductLinks->handle($product, $type, $request->linkedProductIds());

        return new ProductLinksResource($product->load(ProductLinksResource::RELATIONS));
    }
}
