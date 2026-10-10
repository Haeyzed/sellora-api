<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Inventory\Http\Requests\ViewStockRequest;
use App\Tenant\Inventory\Http\Resources\StockLocationResource;
use App\Tenant\Inventory\Models\StockLocation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The places the store keeps stock. Core has one, the default.
 */
final class StockLocationController extends Controller
{
    /**
     * List locations.
     *
     * Needs the inventory.view permission. Active locations, the default first.
     */
    public function index(ViewStockRequest $request): AnonymousResourceCollection
    {
        return StockLocationResource::collection(StockLocation::query()->active()->orderByDesc('is_default')->orderBy('id')->cursorPaginate($request->perPage()));
    }
}
