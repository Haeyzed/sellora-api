<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\RestoreCategory;
use App\Tenant\Catalog\Exceptions\CategoryParentInTrashException;
use App\Tenant\Catalog\Exceptions\CategoryRestoreConflictException;
use App\Tenant\Catalog\Exceptions\CategoryTooDeepException;
use App\Tenant\Catalog\Http\Requests\ManageCategoryRequest;
use App\Tenant\Catalog\Http\Resources\CategoryResource;
use App\Tenant\Catalog\Models\Category;

/**
 * Bringing a category back from the trash.
 */
final class RestoreCategoryController extends Controller
{
    /**
     * Restore a category.
     *
     * Needs the catalog.manage permission. It goes last among its siblings.
     * Refused while its parent is in the trash, while another category uses
     * its slug, or when its parent now sits too deep. Restoring a category
     * that isn't in the trash changes nothing.
     *
     * @throws CategoryParentInTrashException
     * @throws CategoryRestoreConflictException
     * @throws CategoryTooDeepException
     */
    public function __invoke(ManageCategoryRequest $request, Category $category, RestoreCategory $restoreCategory): CategoryResource
    {
        return new CategoryResource($restoreCategory->handle($category)->load('parent:id,public_id')->loadCount('children'));
    }
}
