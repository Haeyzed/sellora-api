<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\ReorderCategories;
use App\Tenant\Catalog\Exceptions\CategoryOrderMismatchException;
use App\Tenant\Catalog\Http\Requests\ReorderCategoriesRequest;
use Illuminate\Http\Response;

/**
 * Putting categories in a new order among their siblings.
 */
final class ReorderCategoriesController extends Controller
{
    /**
     * Reorder categories.
     *
     * Needs the catalog.manage permission. Send the parent (null for the top
     * level) and every one of its subcategories outside the trash, exactly
     * once, in the new order.
     *
     * @throws CategoryOrderMismatchException
     */
    public function __invoke(ReorderCategoriesRequest $request, ReorderCategories $reorderCategories): Response
    {
        $reorderCategories->handle($request->parent(), $request->orderedPublicIds());

        return response()->noContent();
    }
}
