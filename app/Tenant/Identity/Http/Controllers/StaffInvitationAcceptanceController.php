<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Controllers;

use App\Shared\Auth\AccessTokenResource;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Identity\Actions\AcceptStaffInvitation;
use App\Tenant\Identity\Actions\FindPendingStaffInvitation;
use App\Tenant\Identity\Exceptions\InvalidStaffInvitationException;
use App\Tenant\Identity\Exceptions\StaffMemberAlreadyExistsException;
use App\Tenant\Identity\Http\Requests\AcceptStaffInvitationRequest;
use App\Tenant\Identity\Http\Requests\StaffInvitationTokenRequest;
use App\Tenant\Identity\Http\Resources\StaffInvitationPreviewResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Joining a store's team from an emailed invitation link.
 */
final class StaffInvitationAcceptanceController extends Controller
{
    /**
     * Look up an invitation.
     *
     * Shows who the link is for and the roles they will have, so the
     * dashboard can ask for a name and password.
     *
     * @unauthenticated
     *
     * @throws InvalidStaffInvitationException
     */
    public function show(StaffInvitationTokenRequest $request, FindPendingStaffInvitation $findPendingStaffInvitation): StaffInvitationPreviewResource
    {
        return new StaffInvitationPreviewResource($findPendingStaffInvitation->handle($request->token()));
    }

    /**
     * Accept an invitation.
     *
     * Creates the staff member account with the chosen name and password and
     * returns a bearer token, valid for 24 hours, usable only on this store.
     * Each link works once.
     *
     * @unauthenticated
     *
     * @throws InvalidStaffInvitationException
     * @throws StaffMemberAlreadyExistsException
     * @throws UsageLimitReachedException
     */
    public function store(AcceptStaffInvitationRequest $request, AcceptStaffInvitation $acceptStaffInvitation): JsonResponse
    {
        $issuedAccessToken = $acceptStaffInvitation->handle(
            $request->token(),
            $request->chosenName(),
            $request->chosenPassword(),
            $request->deviceName(),
        );

        return (new AccessTokenResource($issuedAccessToken))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
