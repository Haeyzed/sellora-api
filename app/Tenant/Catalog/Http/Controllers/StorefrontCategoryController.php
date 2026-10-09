<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Http\Requests\ListStorefrontCatalogRequest;
use App\Tenant\Catalog\Http\Resources\StorefrontCategoryResource;
use App\Tenant\Catalog\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The store's categories, for the storefront. Open to anyone, rate-limited per visitor.
 */
final class StorefrontCategoryController extends Controller
{
    /**
     * List categories.
     *
     * Every category outside the trash, each with its parent's ID, in the
     * store's order. Texts in the language asked for, or the store's default.
     */
    public function index(ListStorefrontCatalogRequest $request): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->with(['parent:id,public_id', 'media'])
            ->orderBy('position')
            ->orderBy('id')
            ->cursorPaginate($request->perPage());

        return StorefrontCategoryResource::collection($categories);
    }
}
