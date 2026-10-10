<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Controllers;

use App\Shared\Auth\AccountReference;
use App\Shared\Http\Controller;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Actions\AdjustStock;
use App\Tenant\Inventory\Actions\ChangeInventorySettings;
use App\Tenant\Inventory\Actions\ReceiveStock;
use App\Tenant\Inventory\Exceptions\StockWouldGoNegativeException;
use App\Tenant\Inventory\Http\Requests\AdjustStockRequest;
use App\Tenant\Inventory\Http\Requests\ReceiveStockRequest;
use App\Tenant\Inventory\Http\Requests\UpdateInventorySettingsRequest;
use App\Tenant\Inventory\Http\Requests\ViewStockRequest;
use App\Tenant\Inventory\Http\Resources\StockMovementResource;
use App\Tenant\Inventory\Http\Resources\VariantStockResource;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Inventory\Services\VariantStocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * One variant's stock: its levels, its history, and changing it.
 */
final class VariantStockController extends Controller
{
    /**
     * Show a variant's stock.
     *
     * Needs the inventory.view permission. How its stock is handled and its
     * level at every active location.
     */
    public function show(ViewStockRequest $request, ProductVariant $variant, VariantStocks $variantStocks): VariantStockResource
    {
        return new VariantStockResource($variantStocks->of($variant));
    }

    /**
     * Change how a variant's stock is handled.
     *
     * Needs the inventory.adjust permission. Whether it is counted, whether it
     * may be ordered when none is left, and its own low-stock threshold. Send
     * only what changes. Audited.
     */
    public function update(UpdateInventorySettingsRequest $request, ProductVariant $variant, ChangeInventorySettings $changeInventorySettings, VariantStocks $variantStocks): VariantStockResource
    {
        $changeInventorySettings->handle($variant, $request->changes());

        return new VariantStockResource($variantStocks->of($variant));
    }

    /**
     * Correct a variant's stock.
     *
     * Needs the inventory.adjust permission. By an amount ("change": -2) or to
     * a counted number ("count": 17), with a reason. On hand can't go below
     * zero. Recorded in the variant's stock history.
     *
     * @throws StockWouldGoNegativeException
     */
    public function adjust(AdjustStockRequest $request, ProductVariant $variant, AdjustStock $adjustStock, VariantStocks $variantStocks): JsonResponse
    {
        $adjustStock->handle($variant, $request->stockLocation(), $request->change(), $request->count(), $request->reason(), $request->note(), AccountReference::to($request->actor()));

        return (new VariantStockResource($variantStocks->of($variant)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Receive stock.
     *
     * Needs the inventory.adjust permission. Adds units that arrived, such as
     * a delivery, recorded in the variant's stock history.
     */
    public function receive(ReceiveStockRequest $request, ProductVariant $variant, ReceiveStock $receiveStock, VariantStocks $variantStocks): JsonResponse
    {
        $receiveStock->handle($variant, $request->stockLocation(), $request->quantity(), $request->note(), AccountReference::to($request->actor()));

        return (new VariantStockResource($variantStocks->of($variant)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * List a variant's stock history.
     *
     * Needs the inventory.view permission. Every change, newest first, with
     * who or what caused it. Also for a variant in the trash.
     */
    public function movements(ViewStockRequest $request, ProductVariant $variant): AnonymousResourceCollection
    {
        $movements = StockMovement::query()
            ->with('location:id,public_id')
            ->where('product_variant_id', $variant->id)
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage());

        return StockMovementResource::collection($movements);
    }
}
