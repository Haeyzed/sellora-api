<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Inventory\Http\Requests\ListStockLevelsRequest;
use App\Tenant\Inventory\Http\Resources\StockLevelResource;
use App\Tenant\Inventory\Services\StockLevels;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use LogicException;
use stdClass;

/**
 * Stock levels across the catalog, for staff.
 */
final class StockLevelController extends Controller
{
    /**
     * List stock levels.
     *
     * Needs the inventory.view permission. Every variant outside the trash at
     * one location (the default unless chosen), whether or not it has ever had
     * stock. Narrow to variants running low or out of stock, or to a product.
     */
    public function index(ListStockLevelsRequest $request, StockLevels $stockLevels): AnonymousResourceCollection
    {
        $page = $stockLevels->at($request->stockLocation()->id, $request->status(), $request->productId())->cursorPaginate($request->perPage());

        if (! $page instanceof CursorPaginator) {
            throw new LogicException('Stock levels are paged with a cursor.');
        }

        return StockLevelResource::collection($page->through(static fn (stdClass $row): StockLevelResource => new StockLevelResource(StockLevels::fromRow($row))));
    }
}
