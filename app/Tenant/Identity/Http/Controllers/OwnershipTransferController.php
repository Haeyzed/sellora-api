<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Shared\Tenancy\Exceptions\TermsOfServiceNotAcceptedException;
use App\Tenant\Identity\Actions\AcceptOwnershipTransfer;
use App\Tenant\Identity\Actions\CancelOwnershipTransfer;
use App\Tenant\Identity\Actions\FindPendingOwnershipTransfer;
use App\Tenant\Identity\Actions\StartOwnershipTransfer;
use App\Tenant\Identity\Exceptions\InvalidOwnershipTransferRecipientException;
use App\Tenant\Identity\Exceptions\OwnershipTransferAlreadyPendingException;
use App\Tenant\Identity\Exceptions\OwnershipTransferNotFoundException;
use App\Tenant\Identity\Exceptions\OwnershipTransferNotPendingException;
use App\Tenant\Identity\Exceptions\RoleNotAssignableException;
use App\Tenant\Identity\Http\Requests\AcceptOwnershipTransferRequest;
use App\Tenant\Identity\Http\Requests\CancelOwnershipTransferRequest;
use App\Tenant\Identity\Http\Requests\StartOwnershipTransferRequest;
use App\Tenant\Identity\Http\Requests\ViewOwnershipTransferRequest;
use App\Tenant\Identity\Http\Resources\OwnershipTransferResource;
use App\Tenant\Identity\Models\OwnershipTransfer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Handing the store to a new owner: the owner offers it, the chosen colleague accepts it while signed in.
 */
final class OwnershipTransferController extends Controller
{
    /**
     * Show the pending ownership transfer.
     *
     * Only the owner who offered the store and the colleague it was offered
     * to can see it; for everyone else, and when none is pending, 404.
     *
     * @throws OwnershipTransferNotFoundException
     */
    public function pending(ViewOwnershipTransferRequest $request, FindPendingOwnershipTransfer $findPendingOwnershipTransfer): OwnershipTransferResource
    {
        return new OwnershipTransferResource($findPendingOwnershipTransfer->handle($request->actor()));
    }

    /**
     * Offer the store to a colleague.
     *
     * Owner only. Needs your current password, plus an authenticator code (or
     * a recovery code) when you use two-factor authentication. The colleague
     * must be active staff; they are emailed and have 72 hours to accept
     * while signed in. Nothing changes until they accept. Choose the roles you
     * keep afterwards; an empty list leaves you with none. Only one transfer
     * can be pending at a time.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws InvalidTwoFactorCodeException
     * @throws TooManyIncorrectAttemptsException
     * @throws InvalidOwnershipTransferRecipientException
     * @throws RoleNotAssignableException
     * @throws OwnershipTransferAlreadyPendingException
     */
    public function store(StartOwnershipTransferRequest $request, StartOwnershipTransfer $startOwnershipTransfer): JsonResponse
    {
        $ownershipTransfer = $startOwnershipTransfer->handle(
            $request->actor(),
            $request->recipient(),
            $request->currentPassword(),
            $request->code(),
            $request->recoveryCode(),
            $request->roles(),
        );

        return (new OwnershipTransferResource($ownershipTransfer))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Cancel the offer.
     *
     * Owner only, while it is pending.
     *
     * @throws OwnershipTransferNotPendingException
     */
    public function destroy(CancelOwnershipTransferRequest $request, OwnershipTransfer $ownershipTransfer, CancelOwnershipTransfer $cancelOwnershipTransfer): Response
    {
        $cancelOwnershipTransfer->handle($request->actor(), $ownershipTransfer);

        return response()->noContent();
    }

    /**
     * Accept the store.
     *
     * Only the colleague it was offered to, while signed in, within 72 hours.
     * Send the ID of the terms of service version in force: the store's
     * contract with Sellora becomes yours. You become the owner, and the
     * previous owner keeps only the roles they chose. Both of you are emailed.
     *
     * @throws AuthorizationException
     * @throws OwnershipTransferNotPendingException
     * @throws TermsOfServiceNotAcceptedException
     */
    public function accept(AcceptOwnershipTransferRequest $request, OwnershipTransfer $ownershipTransfer, AcceptOwnershipTransfer $acceptOwnershipTransfer): OwnershipTransferResource
    {
        return new OwnershipTransferResource($acceptOwnershipTransfer->handle(
            $request->actor(),
            $ownershipTransfer,
            $request->acceptedTermsOfServiceId(),
            $request->ip(),
            $request->userAgent(),
        ));
    }
}
