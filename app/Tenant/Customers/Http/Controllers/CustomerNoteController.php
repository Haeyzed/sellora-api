<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\ManageCustomerNotes;
use App\Tenant\Customers\Http\Requests\AddCustomerNoteRequest;
use App\Tenant\Customers\Http\Requests\ListCustomerNotesRequest;
use App\Tenant\Customers\Http\Requests\ManageCustomerRequest;
use App\Tenant\Customers\Http\Resources\CustomerNoteResource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Staff notes about a customer. The customer can ask to see them in their data export, without who wrote each.
 */
final class CustomerNoteController extends Controller
{
    /**
     * List a customer's notes.
     *
     * Needs the customers.view permission. Newest first.
     */
    public function index(ListCustomerNotesRequest $request, Customer $customer): AnonymousResourceCollection
    {
        return CustomerNoteResource::collection($customer->notes()->with('author')->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Add a note.
     *
     * Needs the customers.manage permission. Notes can't be edited, only deleted.
     */
    public function store(AddCustomerNoteRequest $request, Customer $customer, ManageCustomerNotes $manageCustomerNotes): JsonResponse
    {
        $note = $manageCustomerNotes->add($request->actor(), $customer, $request->body());

        return (new CustomerNoteResource($note->load('author')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Delete a note.
     *
     * Needs the customers.manage permission.
     */
    public function destroy(ManageCustomerRequest $request, Customer $customer, CustomerNote $note, ManageCustomerNotes $manageCustomerNotes): Response
    {
        $manageCustomerNotes->delete($request->actor(), $note);

        return response()->noContent();
    }
}
