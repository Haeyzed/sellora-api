<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Http\Requests\ListStorefrontCatalogRequest;
use App\Tenant\Catalog\Http\Resources\StorefrontBrandResource;
use App\Tenant\Catalog\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The store's brands, for the storefront. Open to anyone, rate-limited per visitor.
 */
final class StorefrontBrandController extends Controller
{
    /**
     * List brands.
     *
     * Every brand outside the trash, by slug. Texts in the language asked
     * for, or the store's default.
     */
    public function index(ListStorefrontCatalogRequest $request): AnonymousResourceCollection
    {
        return StorefrontBrandResource::collection(Brand::query()->with('media')->orderBy('slug')->orderBy('id')->cursorPaginate($request->perPage()));
    }
}
