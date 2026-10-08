<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\CreateCategory;
use App\Tenant\Catalog\Actions\TrashCategory;
use App\Tenant\Catalog\Actions\UpdateCategory;
use App\Tenant\Catalog\Exceptions\CategoryHasSubcategoriesException;
use App\Tenant\Catalog\Exceptions\CategoryLoopException;
use App\Tenant\Catalog\Exceptions\CategorySlugTakenException;
use App\Tenant\Catalog\Exceptions\CategoryTooDeepException;
use App\Tenant\Catalog\Http\Requests\ListCategoriesRequest;
use App\Tenant\Catalog\Http\Requests\ManageCategoryRequest;
use App\Tenant\Catalog\Http\Requests\StoreCategoryRequest;
use App\Tenant\Catalog\Http\Requests\UpdateCategoryRequest;
use App\Tenant\Catalog\Http\Requests\ViewCategoryRequest;
use App\Tenant\Catalog\Http\Resources\CategoryResource;
use App\Tenant\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's category tree, such as "Clothing > Men > Shirts", for staff managing the catalog.
 */
final class CategoryController extends Controller
{
    /**
     * List categories.
     *
     * Needs the catalog.view permission. Send parent=root for the top level,
     * or a category's ID for its subcategories; left out, every category is
     * listed. Sorted by position, then slug. Send trashed=true for the
     * categories in the trash instead.
     */
    public function index(ListCategoriesRequest $request): AnonymousResourceCollection
    {
        $query = Category::query()
            ->with('parent:id,public_id')
            ->withCount('children')
            ->when($request->wantsTrashed(), static fn ($query) => $query->onlyTrashed());
        $request->applyParentFilter($query);

        return CategoryResource::collection($query->orderBy('position')->orderBy('slug')->orderBy('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Add a category.
     *
     * Needs the catalog.manage permission. The name must include the store's
     * default language. Without a parent it goes at the top level; either
     * way it goes last among its siblings. Without a slug, one is made from
     * the name.
     *
     * @throws CategoryTooDeepException
     * @throws CategorySlugTakenException
     */
    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): JsonResponse
    {
        $category = $createCategory->handle($request->categoryData());

        return (new CategoryResource($this->withDetails($category)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a category.
     *
     * Needs the catalog.view permission. Also shows a category in the trash.
     */
    public function show(ViewCategoryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource($this->withDetails($category));
    }

    /**
     * Change or move a category.
     *
     * Needs the catalog.manage permission. Send only what changes; for texts,
     * only the languages sent change. Moving takes the whole branch along and
     * can't put a category under itself or go deeper than the store allows.
     * Renaming doesn't change the slug. A category in the trash must be
     * restored first.
     *
     * @throws CategoryLoopException
     * @throws CategoryTooDeepException
     * @throws CategorySlugTakenException
     */
    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $updateCategory): CategoryResource
    {
        return new CategoryResource($this->withDetails($updateCategory->handle($category, $request->changes())));
    }

    /**
     * Move a category to the trash.
     *
     * Needs the catalog.manage permission. Only a category without
     * subcategories outside the trash can go. Customers stop seeing it; old
     * orders keep it, and it can be restored.
     *
     * @throws CategoryHasSubcategoriesException
     */
    public function destroy(ManageCategoryRequest $request, Category $category, TrashCategory $trashCategory): Response
    {
        $trashCategory->handle($category);

        return response()->noContent();
    }

    private function withDetails(Category $category): Category
    {
        return $category->load('parent:id,public_id')->loadCount('children');
    }
}
