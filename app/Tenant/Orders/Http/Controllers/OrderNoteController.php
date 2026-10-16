<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Orders\Actions\ManageOrderNotes;
use App\Tenant\Orders\Http\Requests\AddOrderNoteRequest;
use App\Tenant\Orders\Http\Requests\ManageOrderRequest;
use App\Tenant\Orders\Http\Requests\ViewOrderRequest;
use App\Tenant\Orders\Http\Resources\OrderNoteResource;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderNote;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Staff's notes on an order, for each other. Never shown to the buyer.
 */
final class OrderNoteController extends Controller
{
    /**
     * List an order's notes.
     *
     * Needs the orders.view permission. Newest first.
     */
    public function index(ViewOrderRequest $request, Order $order): AnonymousResourceCollection
    {
        return OrderNoteResource::collection($order->notes()->with('author')->latest('id')->get());
    }

    /**
     * Add a note to an order.
     *
     * Needs the orders.manage permission.
     */
    public function store(AddOrderNoteRequest $request, Order $order, ManageOrderNotes $manageOrderNotes): OrderNoteResource
    {
        return new OrderNoteResource($manageOrderNotes->add($request->actor(), $order, $request->body()));
    }

    /**
     * Delete a note on an order.
     *
     * Needs the orders.manage permission.
     */
    public function destroy(ManageOrderRequest $request, Order $order, OrderNote $note, ManageOrderNotes $manageOrderNotes): Response
    {
        $manageOrderNotes->delete($request->actor(), $order, $note);

        return response()->noContent();
    }
}
