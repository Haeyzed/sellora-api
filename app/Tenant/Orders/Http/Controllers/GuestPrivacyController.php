<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Shared\Privacy\ErasureOutcomeResource;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Orders\Actions\EraseGuest;
use App\Tenant\Orders\Http\Requests\GuestPrivacyRequest;
use App\Tenant\Orders\Privacy\GuestDataSubject;
use Illuminate\Http\JsonResponse;

/**
 * Staff answering a guest's data-protection request: someone who ordered without an account, known by the email they ordered with.
 */
final class GuestPrivacyController extends Controller
{
    /**
     * Get a guest's personal data.
     *
     * Needs the customers.privacy permission. Everything the store holds about
     * the guest, by section: their guest orders. Check the request came from
     * the email's owner first. The email is sent in the body, never the URL,
     * so it stays out of logs.
     */
    public function show(GuestPrivacyRequest $request, PersonalDataRegistry $personalDataRegistry): JsonResponse
    {
        return response()->json(['data' => $personalDataRegistry->export(GuestDataSubject::of($request->email()))]);
    }

    /**
     * Erase a guest.
     *
     * Needs the customers.privacy permission and your own password. Their
     * orders are anonymised once settled (an unpaid one is cancelled first);
     * the amounts, tax and country stay. It can't be undone. Invoices and
     * credit notes keep their details, as tax law requires, until the date
     * returned; tell the guest.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function destroy(GuestPrivacyRequest $request, EraseGuest $eraseGuest): ErasureOutcomeResource
    {
        return new ErasureOutcomeResource($eraseGuest->byStaff($request->actor(), $request->email(), $request->string('password')->value()));
    }
}
