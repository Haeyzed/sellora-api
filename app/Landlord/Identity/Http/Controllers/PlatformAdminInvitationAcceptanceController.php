<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Http\Controllers;

use App\Landlord\Identity\Actions\AcceptPlatformAdminInvitation;
use App\Landlord\Identity\Actions\FindPendingPlatformAdminInvitation;
use App\Landlord\Identity\Exceptions\InvalidPlatformAdminInvitationException;
use App\Landlord\Identity\Exceptions\PlatformAdminAlreadyExistsException;
use App\Landlord\Identity\Http\Requests\AcceptPlatformAdminInvitationRequest;
use App\Landlord\Identity\Http\Requests\PlatformAdminInvitationTokenRequest;
use App\Landlord\Identity\Http\Resources\PlatformAdminInvitationPreviewResource;
use App\Shared\Auth\AccessTokenResource;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Joining Sellora's team from an emailed invitation link.
 */
final class PlatformAdminInvitationAcceptanceController extends Controller
{
    /**
     * Look up an invitation.
     *
     * Shows who the link is for and the roles they will have, so the admin
     * app can ask for a name and password.
     *
     * @unauthenticated
     *
     * @throws InvalidPlatformAdminInvitationException
     */
    public function show(PlatformAdminInvitationTokenRequest $request, FindPendingPlatformAdminInvitation $findPendingPlatformAdminInvitation): PlatformAdminInvitationPreviewResource
    {
        return new PlatformAdminInvitationPreviewResource($findPendingPlatformAdminInvitation->handle($request->token()));
    }

    /**
     * Accept an invitation.
     *
     * Creates the platform admin account with the chosen name and password
     * and returns a bearer token. Until two-factor authentication is set up,
     * the token can only set it up, show the profile and sign out. Each link
     * works once.
     *
     * @unauthenticated
     *
     * @throws InvalidPlatformAdminInvitationException
     * @throws PlatformAdminAlreadyExistsException
     */
    public function store(AcceptPlatformAdminInvitationRequest $request, AcceptPlatformAdminInvitation $acceptPlatformAdminInvitation): JsonResponse
    {
        $issuedAccessToken = $acceptPlatformAdminInvitation->handle($request->token(), $request->chosenName(), $request->chosenPassword(), $request->deviceName());

        return (new AccessTokenResource($issuedAccessToken))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
